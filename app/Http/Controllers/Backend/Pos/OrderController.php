<?php

namespace App\Http\Controllers\Backend\Pos;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\PosCart;
use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $orders = Order::with("customer")->get();
            return DataTables::of($orders)
                ->addIndexColumn()
                ->addColumn("saleId", fn($data) => "#" . $data->id)
                ->addColumn("customer", fn($data) => $data->customer->name ?? "-")
                ->addColumn("item", fn($data) => $data->total_item)
                ->addColumn("order_type", function ($data) {
                    if (($data->order_type ?? "takeaway") === "dine_in") {
                        return "<span class=\"badge\" style=\"background:#e8724a;color:#fff;border-radius:4px;padding:3px 8px;\">В зале</span>";
                    }
                    return "<span class=\"badge bg-secondary\" style=\"border-radius:4px;padding:3px 8px;\">На вынос</span>";
                })
                ->addColumn("sub_total", fn($data) => number_format($data->sub_total, 2, ".", ","))
                ->addColumn("discount",  fn($data) => number_format($data->discount, 2, ".", ","))
                ->addColumn("total",     fn($data) => number_format($data->total, 2, ".", ","))
                ->addColumn("paid",      fn($data) => number_format($data->paid, 2, ".", ","))
                ->addColumn("due",       fn($data) => number_format($data->due, 2, ".", ","))
                ->addColumn("status", function ($data) {
                    if ($data->is_returned) {
                        return "<span class=\"badge bg-danger\">Отменён</span>";
                    }
                    return $data->status
                        ? "<span class=\"badge bg-success\">Оплачен</span>"
                        : "<span class=\"badge bg-warning text-dark\">Долг</span>";
                })
                ->addColumn("action", function ($data) {
                    $inv  = route("backend.admin.orders.invoice", $data->id);
                    $pos  = route("backend.admin.orders.pos-invoice", $data->id);
                    $coll = route("backend.admin.due.collection", $data->id);
                    $txn  = route("backend.admin.orders.transactions", $data->id);
                    $cancel = route("backend.admin.orders.cancel", $data->id);

                    $btn  = "<a class=\"btn btn-success btn-sm mb-1\" href=\"{$inv}\"><i class=\"fas fa-file-invoice\"></i> Инвойс</a> ";
                    $btn .= "<a class=\"btn btn-secondary btn-sm mb-1\" href=\"{$pos}\"><i class=\"fas fa-receipt\"></i> Чек</a> ";

                    if (!$data->is_returned) {
                        if (!$data->status) {
                            $btn .= "<a class=\"btn btn-warning btn-sm mb-1\" href=\"{$coll}\"><i class=\"fas fa-money-bill\"></i> Оплатить</a> ";
                        }
                        $btn .= "<button class=\"btn btn-danger btn-sm mb-1 btn-cancel\" data-id=\"{$data->id}\" data-url=\"{$cancel}\"><i class=\"fas fa-ban\"></i> Отменить</button> ";
                    }

                    $btn .= "<a class=\"btn btn-primary btn-sm mb-1\" href=\"{$txn}\"><i class=\"fas fa-exchange-alt\"></i> Транзакции</a>";
                    return $btn;
                })
                ->rawColumns(["saleId","customer","item","order_type","sub_total","discount","total","paid","due","status","action"])
                ->toJson();
        }
        return view("backend.orders.index");
    }

    public function create() {}

    public function store(Request $request)
    {
        $request->validate([
            "customer_id"    => ["required", "exists:customers,id", "integer"],
            "order_discount" => ["nullable", "numeric", "min:0"],
            "paid"           => ["nullable", "numeric", "min:0"],
            "order_type"     => ["nullable", "in:takeaway,dine_in"],
        ]);

        $carts = PosCart::with("product")->where("user_id", auth()->id())->get();

        if ($carts->isEmpty()) {
            return response()->json(["message" => "Корзина пуста."], 422);
        }

        try {
            $order = DB::transaction(function () use ($request, $carts) {

                // 1. Lock products sorted by ID (deadlock prevention)
                $productIds = collect($carts->pluck("product_id"))
                    ->unique()->sort()->values()->toArray();

                $products = Product::whereIn("id", $productIds)
                    ->orderBy("id")->lockForUpdate()->get()->keyBy("id");

                // 2. Guard: product stock
                foreach ($carts as $cart) {
                    $product = $products[$cart->product_id] ?? null;
                    if (!$product || $product->quantity < $cart->quantity) {
                        throw new \DomainException(
                            "Недостаточно товара: " . ($product->name ?? "неизвестный товар")
                        );
                    }
                }

                // 3. Aggregate ingredient requirements
                $ingredientRequired = [];
                foreach ($carts as $cart) {
                    $recipes = Recipe::where("product_id", $cart->product_id)->get();
                    foreach ($recipes as $recipe) {
                        $needed = (string) bcmul((string)$recipe->quantity, (string)$cart->quantity, 3);
                        $ingredientRequired[$recipe->ingredient_id] = bcadd(
                            (string)($ingredientRequired[$recipe->ingredient_id] ?? '0'),
                            $needed, 3
                        );
                    }
                }

                // 4. Lock ingredients + guard stock
                if (!empty($ingredientRequired)) {
                    $ingredientIds = collect(array_keys($ingredientRequired))
                        ->sort()->values()->toArray();

                    $lockedIngredients = Ingredient::whereIn("id", $ingredientIds)
                        ->orderBy("id")->lockForUpdate()->get()->keyBy("id");

                    foreach ($ingredientIds as $ingredientId) {
                        $needed     = $ingredientRequired[$ingredientId];
                        $ingredient = $lockedIngredients[$ingredientId] ?? null;

                        if (!$ingredient) {
                            throw new \DomainException(
                                "Рецепт ссылается на удалённый ингредиент (id={$ingredientId}). Обновите рецепт."
                            );
                        }
                        if (bccomp((string)$ingredient->stock, $needed, 3) < 0) {
                            throw new \DomainException(
                                "Недостаточно на складе: {$ingredient->name}"
                                . " — нужно " . number_format($needed, 3) . " {$ingredient->unit}"
                                . ", есть " . number_format($ingredient->stock, 3) . " {$ingredient->unit}."
                            );
                        }
                    }
                }

                // 5. Create order shell
                $order = Order::create([
                    "customer_id" => $request->customer_id,
                    "user_id"     => $request->user()->id,
                    "order_type"  => $request->order_type ?? "takeaway",
                ]);

                $totalAmountOrder = 0;
                $orderDiscount    = (float) ($request->order_discount ?? 0);

                // 6. Order lines + product stock decrement
                foreach ($carts as $cart) {
                    $product            = $products[$cart->product_id];
                    $mainTotal          = $product->price * $cart->quantity;
                    $totalAfterDiscount = $product->discounted_price * $cart->quantity;
                    $discount           = $mainTotal - $totalAfterDiscount;
                    $totalAmountOrder  += $totalAfterDiscount;

                    $order->products()->create([
                        "quantity"       => $cart->quantity,
                        "price"          => $product->price,
                        "purchase_price" => $product->purchase_price,
                        "sub_total"      => $mainTotal,
                        "discount"       => $discount,
                        "total"          => $totalAfterDiscount,
                        "product_id"     => $product->id,
                    ]);

                    Product::where("id", $product->id)->decrement("quantity", $cart->quantity);
                }

                // 7. Deduct ingredients + log each transaction
                foreach ($ingredientRequired as $ingredientId => $needed) {
                    Ingredient::where("id", $ingredientId)->decrement("stock", $needed);

                    InventoryTransaction::create([
                        'ingredient_id'  => $ingredientId,
                        'type'           => 'consume',
                        'quantity'       => $needed,
                        'reference_type' => 'order',
                        'reference_id'   => $order->id,
                        'user_id'        => auth()->id(),
                        'note'           => "Списание по заказу #{$order->id}",
                    ]);
                }

                // 8. Финансовые итоги
                $orderDiscount = min($orderDiscount, $totalAmountOrder);
                $total         = round($totalAmountOrder - $orderDiscount, 2);
                $total         = max($total, 0);
                $paid          = min(max((float) ($request->paid ?? 0), 0), $total);
                $due           = max(round($total - $paid, 2), 0);

                $order->sub_total = $totalAmountOrder;
                $order->discount  = $orderDiscount;
                $order->paid      = $paid;
                $order->total     = $total;
                $order->due       = $due;
                $order->status    = $due <= 0 ? 1 : 0;
                $order->save();

                if ($paid > 0) {
                    $order->transactions()->create([
                        "amount"      => $paid,
                        "customer_id" => $order->customer_id,
                        "user_id"     => auth()->id(),
                        "paid_by"     => "cash",
                    ]);
                }

                PosCart::where("user_id", auth()->id())->delete();

                return $order;
            });
        } catch (\DomainException $e) {
            return response()->json(["message" => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                "message" => "Не удалось оформить заказ. Попробуйте снова.",
            ], 500);
        }

        return response()->json(["message" => "Заказ успешно оформлен", "order" => $order], 200);
    }

    /**
     * Отмена заказа: возврат товара + ингредиентов + запись в журнал.
     */
    public function cancel(Request $request, $id)
    {
        $order = Order::with(['products'])->findOrFail($id);

        if ($order->is_returned) {
            return response()->json(['message' => 'Заказ уже отменён.'], 422);
        }

        try {
            DB::transaction(function () use ($order) {

                // 1. Lock order
                $order = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

                // 2. Collect ingredient restore requirements
                $ingredientRestore = [];
                foreach ($order->products as $item) {
                    // Restore product stock
                    Product::where('id', $item->product_id)->increment('quantity', $item->quantity);

                    // Collect ingredients
                    $recipes = Recipe::where('product_id', $item->product_id)->get();
                    foreach ($recipes as $recipe) {
                        $qty = bcmul((string)$recipe->quantity, (string)$item->quantity, 3);
                        $ingredientRestore[$recipe->ingredient_id] = bcadd(
                            (string)($ingredientRestore[$recipe->ingredient_id] ?? '0'),
                            $qty, 3
                        );
                    }
                }

                // 3. Lock ingredients sorted by ID (deadlock prevention)
                if (!empty($ingredientRestore)) {
                    $ingredientIds = collect(array_keys($ingredientRestore))->sort()->values()->toArray();
                    Ingredient::whereIn('id', $ingredientIds)->orderBy('id')->lockForUpdate()->get();
                }

                // 4. Restore ingredients + log
                foreach ($ingredientRestore as $ingredientId => $qty) {
                    Ingredient::where('id', $ingredientId)->increment('stock', $qty);

                    InventoryTransaction::create([
                        'ingredient_id'  => $ingredientId,
                        'type'           => 'restore',
                        'quantity'       => $qty,
                        'reference_type' => 'order',
                        'reference_id'   => $order->id,
                        'user_id'        => auth()->id(),
                        'note'           => "Возврат по отмене заказа #{$order->id}",
                    ]);
                }

                // 5. Mark order as returned/cancelled
                $order->is_returned = true;
                $order->status      = 0;
                $order->save();
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Ошибка при отмене заказа: ' . $e->getMessage()], 500);
        }

        return response()->json(['message' => "Заказ #{$id} отменён. Ингредиенты возвращены на склад."]);
    }

    public function show(string $id) {}
    public function edit(string $id) {}
    public function update(Request $request, string $id) {}
    public function destroy(string $id) {}

    public function invoice($id)
    {
        $order = Order::with(["customer", "products.product"])->findOrFail($id);
        return view("backend.orders.print-invoice", compact("order"));
    }

    public function collection(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if ($request->isMethod("post")) {
            $data = $request->validate(["amount" => "required|numeric|min:0.01"]);

            try {
                $orderTransaction = DB::transaction(function () use ($order, $data) {
                    $locked = Order::where("id", $order->id)->lockForUpdate()->firstOrFail();

                    if ($data["amount"] > $locked->due) {
                        throw new \DomainException("Сумма оплаты превышает задолженность.");
                    }

                    $locked->due    = round(max($locked->due - $data["amount"], 0), 2);
                    $locked->paid   = round($locked->paid + $data["amount"], 2);
                    $locked->status = $locked->due <= 0 ? 1 : 0;
                    $locked->save();

                    return $locked->transactions()->create([
                        "amount"      => $data["amount"],
                        "customer_id" => $locked->customer_id,
                        "user_id"     => auth()->id(),
                        "paid_by"     => "cash",
                    ]);
                });
            } catch (\DomainException $e) {
                return back()->withErrors(["amount" => $e->getMessage()]);
            }

            return to_route("backend.admin.collectionInvoice", $orderTransaction->id);
        }

        return view("backend.orders.collection.create", compact("order"));
    }

    public function collectionInvoice($id)
    {
        $transaction       = OrderTransaction::findOrFail($id);
        $collection_amount = $transaction->amount;
        $order             = $transaction->order;
        return view("backend.orders.collection.invoice", compact("order", "collection_amount", "transaction"));
    }

    public function transactions($id)
    {
        $order = Order::with("transactions")->findOrFail($id);
        return view("backend.orders.collection.index", compact("order"));
    }

    public function posInvoice($id)
    {
        $order    = Order::with(["customer", "products.product"])->findOrFail($id);
        $maxWidth = readConfig("receiptMaxwidth") ?? "300px";
        return view("backend.orders.pos-invoice", compact("order", "maxWidth"));
    }
}
