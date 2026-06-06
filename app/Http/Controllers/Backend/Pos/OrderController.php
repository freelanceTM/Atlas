<?php

namespace App\Http\Controllers\Backend\Pos;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\PosCart;
use App\Models\Product;
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
                    $buttons .= '<a class="btn btn-success btn-sm" href="' . route('backend.admin.orders.invoice', $data->id) . '"><i class="fas fa-file-invoice"></i> Invoice</a>';
                    $buttons .= '<a class="btn btn-secondary btn-sm" href="' . route('backend.admin.orders.pos-invoice', $data->id) . '"><i class="fas fa-file-invoice"></i> Pos Invoice</a>';
                    if (!$data->status) {
                        $buttons .= '<a class="btn btn-warning btn-sm" href="' . route('backend.admin.due.collection', $data->id) . '"><i class="fas fa-receipt"></i> Due Collection</a>';
                    }
                    $buttons .= '<a class="btn btn-primary btn-sm" href="' . route('backend.admin.orders.transactions', $data->id) . '"><i class="fas fa-exchange-alt"></i> Transactions</a>';
                    return $buttons;
                })
                ->rawColumns(['saleId', 'customer', 'item', 'sub_total', 'discount', 'total', 'paid', 'due', 'status', 'action'])
                ->toJson();
        }
        return view('backend.orders.index');
    }

    public function create()
    {
        //
    }

    /**
     * SECURITY: Full atomic POS order creation.
     *
     * Fixes applied:
     *  1. Empty-cart guard  — prevents $0 ghost orders
     *  2. lockForUpdate()   — row-level X-lock on each product; eliminates race condition
     *  3. Oversell guard    — stock re-checked inside the locked transaction
     *  4. Atomic decrement  — UPDATE qty = qty - N (no read-modify-write)
     *  5. Discount clamp    — discount cannot exceed subtotal; total can never be negative
     *  6. Paid clamp        — paid cannot exceed total; due can never be negative
     *  7. DB::transaction   — full rollback on any failure
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_id'    => ['required', 'exists:customers,id', 'integer'],
            'order_discount' => ['nullable', 'numeric', 'min:0'],
            'paid'           => ['nullable', 'numeric', 'min:0'],
        ], [
            'customer_id.required'    => 'Please select a customer.',
            'customer_id.exists'      => 'The selected customer does not exist.',
            'order_discount.numeric'  => 'The order discount must be a number.',
            'paid.numeric'            => 'The amount paid must be a number.',
        ]);

        // GUARD 1: reject empty cart before touching the DB
        $cartCount = PosCart::where('user_id', auth()->id())->count();
        if ($cartCount === 0) {
            return response()->json(['message' => 'Cart is empty. Add products before placing an order.'], 422);
        }

        try {
            $order = DB::transaction(function () use ($request) {

                // Load cart items — IDs only first, then lock products one-by-one
                $carts = PosCart::with('product')->where('user_id', auth()->id())->get();

                $order         = Order::create([
                    'customer_id' => $request->customer_id,
                    'user_id'     => $request->user()->id,
                ]);

                $totalAmountOrder = 0;

                foreach ($carts as $cart) {
                    // GUARD 2+3: acquire row-level X-lock, then re-verify stock
                    // lockForUpdate() → SELECT ... FOR UPDATE prevents concurrent reads
                    // between our check and our write (eliminates race condition + oversell)
                    $product = Product::lockForUpdate()->findOrFail($cart->product_id);

                    if ($product->quantity < $cart->quantity) {
                        throw new \RuntimeException(
                            "Insufficient stock for \"{$product->name}\". " .
                            "Available: {$product->quantity}, requested: {$cart->quantity}."
                        );
                    }

                    $mainTotal         = $product->price * $cart->quantity;
                    $totalAfterDiscount = $product->discounted_price * $cart->quantity;
                    $lineDiscount       = $mainTotal - $totalAfterDiscount;
                    $totalAmountOrder  += $totalAfterDiscount;

                    $order->products()->create([
                        'quantity'       => $cart->quantity,
                        'price'          => $product->price,
                        'purchase_price' => $product->purchase_price,
                        'sub_total'      => $mainTotal,
                        'discount'       => $lineDiscount,
                        'total'          => $totalAfterDiscount,
                        'product_id'     => $product->id,
                    ]);

                    // GUARD 4: atomic SQL decrement — no read-modify-write, no race
                    // Generates: UPDATE products SET quantity = quantity - N WHERE id = ?
                    $product->decrement('quantity', $cart->quantity);
                }

                // GUARD 5: discount cannot exceed the order subtotal
                $requestedDiscount = (float) ($request->order_discount ?? 0);
                $orderDiscount     = min($requestedDiscount, $totalAmountOrder);

                $total = $totalAmountOrder - $orderDiscount;

                // GUARD 6: paid cannot exceed the total; prevents negative due
                $requestedPaid = (float) ($request->paid ?? 0);
                $paid          = min($requestedPaid, $total);
                $due           = $total - $paid;

                $order->sub_total = round($totalAmountOrder, 2);
                $order->discount  = round($orderDiscount, 2);
                $order->paid      = round($paid, 2);
                $order->total     = round($total, 2);
                $order->due       = round($due, 2);
                $order->status    = round($due, 2) <= 0;
                $order->save();

                if ($paid > 0) {
                    $order->transactions()->create([
                        'amount'      => round($paid, 2),
                        'customer_id' => $order->customer_id,
                        'user_id'     => auth()->id(),
                        'paid_by'     => 'cash',
                    ]);
                }

                // Cart cleared only after every step succeeds
                PosCart::where('user_id', auth()->id())->delete();

                return $order;
            });

            return response()->json(['message' => 'Order completed successfully', 'order' => $order], 200);

        } catch (\RuntimeException $e) {
            // Business logic failures (insufficient stock, etc.) — 422 Unprocessable
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            // Unexpected DB/infrastructure failures — full rollback guaranteed by DB::transaction
            return response()->json([
                'message' => 'Order failed. All changes have been rolled back.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }

    public function invoice($id)
    {
        $order = Order::with(['customer', 'products.product'])->findOrFail($id);
        return view('backend.orders.print-invoice', compact('order'));
    }

    /**
     * SECURITY: Atomic due collection.
     *
     * Fixes applied:
     *  1. lockForUpdate() on order  — X-lock prevents concurrent POSTs reading same due amount
     *  2. Closed-order guard        — rejects payment on already-settled orders
     *  3. Overpayment guard         — amount capped at current due; no negative due possible
     *  4. DB::transaction           — order update + transaction record are one atomic unit;
     *                                 crash between them is impossible to leave partial state
     */
    public function collection(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if ($request->isMethod('post')) {
            // Pre-validate format before acquiring the lock
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
            ]);

            try {
                $orderTransaction = DB::transaction(function () use ($request, $id) {

                    // GUARD 1: re-fetch with exclusive lock — blocks concurrent POST for same order
                    $order = Order::lockForUpdate()->findOrFail($id);

                    // GUARD 2: reject payment on already-settled order
                    if ($order->status && $order->due <= 0) {
                        throw new \RuntimeException('This order is already fully paid.');
                    }

                    // GUARD 3: amount cannot exceed remaining due — no negative due possible
                    $amount = min((float) $request->amount, (float) $order->due);

                    if ($amount <= 0) {
                        throw new \RuntimeException('Payment amount must be greater than zero.');
                    }

                    $newDue  = round($order->due  - $amount, 2);
                    $newPaid = round($order->paid + $amount, 2);

                    $order->due    = $newDue;
                    $order->paid   = $newPaid;
                    $order->status = $newDue <= 0;
                    $order->save();

                    // Transaction record created in the same atomic unit as order update
                    $orderTransaction = $order->transactions()->create([
                        'amount'      => round($amount, 2),
                        'customer_id' => $order->customer_id,
                        'user_id'     => auth()->id(),
                        'paid_by'     => 'cash',
                    ]);

                    return $orderTransaction;
                });

                return to_route('backend.admin.collectionInvoice', $orderTransaction->id);

            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            } catch (\Throwable $e) {
                return back()->with('error', 'Payment failed. No changes were saved.');
            }
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
