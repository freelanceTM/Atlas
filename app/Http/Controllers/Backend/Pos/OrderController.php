<?php

namespace App\Http\Controllers\Backend\Pos;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
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
            $orders = Order::with('customer')->get();
            return DataTables::of($orders)
                ->addIndexColumn()
                ->addColumn('saleId', fn($data) => "#" . $data->id)
                ->addColumn('customer', fn($data) => $data->customer->name ?? '-')
                ->addColumn('item', fn($data) => $data->total_item)
                ->addColumn('order_type', function ($data) {
                    if (($data->order_type ?? 'takeaway') === 'dine_in') {
                        return '<span class="badge" style="background:#e8724a;color:#fff;border-radius:4px;padding:3px 8px;">🍽 Dine-in</span>';
                    }
                    return '<span class="badge bg-secondary" style="border-radius:4px;padding:3px 8px;">🥡 Takeaway</span>';
                })
                ->addColumn('sub_total', fn($data) => number_format($data->sub_total, 2, '.', ','))
                ->addColumn('discount', fn($data) => number_format($data->discount, 2, '.', ','))
                ->addColumn('total', fn($data) => number_format($data->total, 2, '.', ','))
                ->addColumn('paid', fn($data) => number_format($data->paid, 2, '.', ','))
                ->addColumn('due', fn($data) => number_format($data->due, 2, '.', ','))
                ->addColumn('status', fn($data) => $data->status
                    ? '<span class="badge bg-primary">Paid</span>'
                    : '<span class="badge bg-danger">Due</span>')
                ->addColumn('action', function ($data) {
                    $buttons = '';
                    $buttons .= '<a class="btn btn-success btn-sm" href="' . route('backend.admin.orders.invoice', $data->id) . '"><i class="fas fa-file-invoice"></i> Invoice</a> ';
                    $buttons .= '<a class="btn btn-secondary btn-sm" href="' . route('backend.admin.orders.pos-invoice', $data->id) . '"><i class="fas fa-receipt"></i> Receipt</a> ';
                    if (!$data->status) {
                        $buttons .= '<a class="btn btn-warning btn-sm" href="' . route('backend.admin.due.collection', $data->id) . '"><i class="fas fa-money-bill"></i> Collect</a> ';
                    }
                    $buttons .= '<a class="btn btn-primary btn-sm" href="' . route('backend.admin.orders.transactions', $data->id) . '"><i class="fas fa-exchange-alt"></i> Txn</a>';
                    return $buttons;
                })
                ->rawColumns(['saleId', 'customer', 'item', 'order_type', 'sub_total', 'discount', 'total', 'paid', 'due', 'status', 'action'])
                ->toJson();
        }
        return view('backend.orders.index');
    }

    public function create() {}

    public function store(Request $request)
    {
        $request->validate([
            'customer_id'    => ['required', 'exists:customers,id', 'integer'],
            'order_discount' => ['nullable', 'numeric', 'min:0'],
            'paid'           => ['nullable', 'numeric', 'min:0'],
            'order_type'     => ['nullable', 'in:takeaway,dine_in'],
        ], [
            'customer_id.required'   => 'Please select a customer.',
            'customer_id.exists'     => 'The selected customer does not exist.',
            'order_discount.numeric' => 'The order discount must be a number.',
            'paid.numeric'           => 'The amount paid must be a number.',
            'order_type.in'          => 'Order type must be takeaway or dine_in.',
        ]);

        $carts = PosCart::with('product')->where('user_id', auth()->id())->get();

        if ($carts->isEmpty()) {
            return response()->json(['message' => 'Cart is empty.'], 422);
        }

        // ── FIX BUG-1: Catch domain exceptions and return JSON 422 ────────
        try {
            $order = DB::transaction(function () use ($request, $carts) {

                // ── 1. Lock products (sorted by ID → FIX BUG-3 deadlock) ──────
                $productIds = collect($carts->pluck('product_id'))
                    ->unique()->sort()->values()->toArray();

                $products = Product::whereIn('id', $productIds)
                    ->orderBy('id')          // deterministic lock order
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // ── 2. Guard: product stock ───────────────────────────────────
                foreach ($carts as $cart) {
                    $product = $products[$cart->product_id] ?? null;
                    if (!$product || $product->quantity < $cart->quantity) {
                        throw new \DomainException(
                            'Insufficient product stock for: ' . ($product->name ?? 'unknown product')
                        );
                    }
                }

                // ── 3. Calculate total ingredient requirements ────────────────
                $ingredientRequired = [];
                foreach ($carts as $cart) {
                    $recipes = Recipe::where('product_id', $cart->product_id)->get();
                    foreach ($recipes as $recipe) {
                        $needed = $recipe->quantity * $cart->quantity;
                        $ingredientRequired[$recipe->ingredient_id] =
                            ($ingredientRequired[$recipe->ingredient_id] ?? 0) + $needed;
                    }
                }

                // ── 4. Lock ingredients (sorted by ID → FIX BUG-3 deadlock) ──
                if (!empty($ingredientRequired)) {
                    // Sort keys for deterministic lock ordering — prevents deadlock
                    $ingredientIds = collect(array_keys($ingredientRequired))
                        ->sort()->values()->toArray();

                    $lockedIngredients = Ingredient::whereIn('id', $ingredientIds)
                        ->orderBy('id')      // deterministic lock order
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    foreach ($ingredientIds as $ingredientId) {
                        $needed     = $ingredientRequired[$ingredientId];
                        $ingredient = $lockedIngredients[$ingredientId] ?? null;

                        if (!$ingredient) {
                            throw new \DomainException(
                                'Recipe references a deleted ingredient (id=' . $ingredientId . '). Please update the recipe.'
                            );
                        }

                        if ($ingredient->stock < $needed) {
                            throw new \DomainException(
                                'Insufficient ingredient: ' . $ingredient->name .
                                ' — need ' . round($needed, 3) . ' ' . $ingredient->unit .
                                ', have ' . round($ingredient->stock, 3) . ' ' . $ingredient->unit . '.'
                            );
                        }
                    }
                }

                // ── 5. Create the order ───────────────────────────────────────
                $order = Order::create([
                    'customer_id' => $request->customer_id,
                    'user_id'     => $request->user()->id,
                    'order_type'  => $request->order_type ?? 'takeaway',
                ]);

                $totalAmountOrder = 0;
                $orderDiscount    = (float) ($request->order_discount ?? 0);

                // ── 6. Create order lines + decrement product stock ───────────
                foreach ($carts as $cart) {
                    $product            = $products[$cart->product_id];
                    $mainTotal          = $product->price * $cart->quantity;
                    $totalAfterDiscount = $product->discounted_price * $cart->quantity;
                    $discount           = $mainTotal - $totalAfterDiscount;
                    $totalAmountOrder  += $totalAfterDiscount;

                    $order->products()->create([
                        'quantity'       => $cart->quantity,
                        'price'          => $product->price,
                        'purchase_price' => $product->purchase_price,
                        'sub_total'      => $mainTotal,
                        'discount'       => $discount,
                        'total'          => $totalAfterDiscount,
                        'product_id'     => $product->id,
                    ]);

                    // Atomic decrement — safe under lockForUpdate
                    Product::where('id', $product->id)->decrement('quantity', $cart->quantity);
                }

                // ── 7. Deduct ingredients ─────────────────────────────────────
                foreach ($ingredientRequired as $ingredientId => $needed) {
                    Ingredient::where('id', $ingredientId)->decrement('stock', $needed);
                }

                // ── 8. Finalize order totals ──────────────────────────────────
                $total = $totalAmountOrder - $orderDiscount;
                $paid  = (float) ($request->paid ?? 0);
                $due   = $total - $paid;

                $order->sub_total = $totalAmountOrder;
                $order->discount  = $orderDiscount;
                $order->paid      = $paid;
                $order->total     = round($total, 2);
                $order->due       = round($due, 2);
                $order->status    = round($due, 2) <= 0 ? 1 : 0;
                $order->save();

                if ($paid > 0) {
                    $order->transactions()->create([
                        'amount'      => $paid,
                        'customer_id' => $order->customer_id,
                        'user_id'     => auth()->id(),
                        'paid_by'     => 'cash',
                    ]);
                }

                PosCart::where('user_id', auth()->id())->delete();

                return $order;
            });
        } catch (\DomainException $e) {
            // Domain exceptions (stock/ingredient guards) → friendly 422
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            // Unexpected DB errors → generic 500 with safe message
            return response()->json([
                'message' => 'Order could not be completed due to a server error. Please try again.',
            ], 500);
        }

        return response()->json([
            'message' => 'Order completed successfully',
            'order'   => $order,
        ], 200);
    }

    public function show(string $id) {}
    public function edit(string $id) {}
    public function update(Request $request, string $id) {}
    public function destroy(string $id) {}

    public function invoice($id)
    {
        $order = Order::with(['customer', 'products.product'])->findOrFail($id);
        return view('backend.orders.print-invoice', compact('order'));
    }

    public function collection(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'amount' => 'required|numeric|min:0.01',
            ]);

            try {
                $orderTransaction = DB::transaction(function () use ($order, $data) {
                    $locked = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

                    if ($data['amount'] > $locked->due) {
                        throw new \DomainException('Payment amount exceeds the outstanding due.');
                    }

                    $locked->due    = round($locked->due  - $data['amount'], 2);
                    $locked->paid   = round($locked->paid + $data['amount'], 2);
                    $locked->status = $locked->due <= 0 ? 1 : 0;
                    $locked->save();

                    return $locked->transactions()->create([
                        'amount'      => $data['amount'],
                        'customer_id' => $locked->customer_id,
                        'user_id'     => auth()->id(),
                        'paid_by'     => 'cash',
                    ]);
                });
            } catch (\DomainException $e) {
                return back()->withErrors(['amount' => $e->getMessage()]);
            }

            return to_route('backend.admin.collectionInvoice', $orderTransaction->id);
        }

        return view('backend.orders.collection.create', compact('order'));
    }

    public function collectionInvoice($id)
    {
        $transaction       = OrderTransaction::findOrFail($id);
        $collection_amount = $transaction->amount;
        $order             = $transaction->order;
        return view('backend.orders.collection.invoice', compact('order', 'collection_amount', 'transaction'));
    }

    public function transactions($id)
    {
        $order = Order::with('transactions')->findOrFail($id);
        return view('backend.orders.collection.index', compact('order'));
    }

    public function posInvoice($id)
    {
        $order    = Order::with(['customer', 'products.product'])->findOrFail($id);
        $maxWidth = readConfig('receiptMaxwidth') ?? '300px';
        return view('backend.orders.pos-invoice', compact('order', 'maxWidth'));
    }
}
