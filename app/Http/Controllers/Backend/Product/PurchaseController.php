<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        abort_if(!auth()->user()->can('purchase_view'), 403);
        if ($request->ajax()) {
            $purchases = Purchase::with('supplier')->latest()->get();
            return DataTables::of($purchases)
                ->addIndexColumn()
                ->addColumn('supplier', fn($data) => $data->supplier->name)
                ->addColumn('id', fn($data) => '#' . $data->id)
                ->addColumn('total', fn($data) => $data->grand_total)
                ->addColumn('created_at', fn($data) => Carbon::parse($data->date)->format('d M, Y'))
                ->addColumn('action', function ($data) {
                    return '<div class="btn-group">
                    <button type="button" class="btn bg-gradient-primary btn-flat">Action</button>
                    <button type="button" class="btn bg-gradient-primary btn-flat dropdown-toggle dropdown-icon" data-toggle="dropdown" aria-expanded="false">
                      <span class="sr-only">Toggle Dropdown</span>
                    </button>
                    <div class="dropdown-menu" role="menu">
                      <a class="dropdown-item" href="' . route('backend.admin.purchase.create', ['purchase_id' => $data->id]) . '">
                        <i class="fas fa-edit"></i> Edit
                      </a>
                      <a class="dropdown-item" href="' . route('backend.admin.purchase.products', $data->id) . '">
                        <i class="fas fa-eye"></i> View
                      </a>
                    </div>
                  </div>';
                })
                ->rawColumns(['supplier', 'id', 'total', 'created_at', 'action'])
                ->toJson();
        }
        return view('backend.purchase.index');
    }

    public function create()
    {
        abort_if(!auth()->user()->can('purchase_create'), 403);
        return view('backend.purchase.create');
    }

    /**
     * SECURITY: Atomic purchase creation and editing.
     *
     * CREATE path fixes:
     *  1. lockForUpdate() on each product — prevents concurrent purchases inflating the same stock
     *
     * EDIT path fixes:
     *  2. lockForUpdate() on product — eliminates race condition on concurrent edits
     *  3. Negative-stock guard — decrement is capped so quantity never goes below 0
     *  4. Duplicate-item guard — updateOrCreate only uses 'id' key when item_id is a valid int > 0;
     *     null id previously always created a new row (duplicate PurchaseItem + double stock)
     */
    public function store(Request $request)
    {
        abort_if(!auth()->user()->can('purchase_create'), 403);

        if (!$request->wantsJson()) {
            return;
        }

        $validatedData = $request->validate([
            'products'           => 'required|array',
            'purchase_id'        => 'nullable|integer',
            'date'               => 'nullable|date',
            'supplierId'         => 'required|exists:suppliers,id',
            'totals'             => 'required|array',
            'totals.subTotal'    => 'required|numeric',
            'totals.tax'         => 'nullable|numeric',
            'totals.discount'    => 'nullable|numeric',
            'totals.shipping'    => 'nullable|numeric',
            'totals.grandTotal'  => 'required|numeric',
        ]);

        try {
            $purchase = DB::transaction(function () use ($validatedData) {

                $isNew = empty($validatedData['purchase_id']);

                if ($isNew) {
                    // ── CREATE path ──────────────────────────────────────────
                    $purchase = Purchase::create([
                        'supplier_id'    => $validatedData['supplierId'],
                        'user_id'        => auth()->id(),
                        'sub_total'      => $validatedData['totals']['subTotal'],
                        'tax'            => $validatedData['totals']['tax'] ?? 0,
                        'discount_value' => $validatedData['totals']['discount'] ?? 0,
                        'shipping'       => $validatedData['totals']['shipping'] ?? 0,
                        'grand_total'    => $validatedData['totals']['grandTotal'],
                        'date'           => $validatedData['date'] ?? Carbon::now()->toDateString(),
                        'status'         => 1,
                    ]);

                    foreach ($validatedData['products'] as $product) {
                        // GUARD 1: X-lock prevents concurrent purchases racing on same product
                        $existingProduct = Product::lockForUpdate()->findOrFail($product['id']);

                        PurchaseItem::create([
                            'purchase_id'    => $purchase->id,
                            'product_id'     => $product['id'],
                            'purchase_price' => $product['purchase_price'],
                            'price'          => $product['price'],
                            'quantity'       => $product['qty'],
                        ]);

                        // Atomic SQL: UPDATE products SET quantity = quantity + N WHERE id = ?
                        $existingProduct->increment('quantity', $product['qty']);
                    }

                } else {
                    // ── EDIT path ────────────────────────────────────────────
                    $purchase = Purchase::findOrFail($validatedData['purchase_id']);
                    $purchase->update([
                        'supplier_id'    => $validatedData['supplierId'],
                        'user_id'        => auth()->id(),
                        'sub_total'      => $validatedData['totals']['subTotal'],
                        'tax'            => $validatedData['totals']['tax'] ?? 0,
                        'discount_value' => $validatedData['totals']['discount'] ?? 0,
                        'shipping'       => $validatedData['totals']['shipping'] ?? 0,
                        'grand_total'    => $validatedData['totals']['grandTotal'],
                        'date'           => $validatedData['date'] ?? Carbon::now()->toDateString(),
                        'status'         => 1,
                    ]);

                    foreach ($validatedData['products'] as $product) {
                        // GUARD 2: X-lock on product before any stock arithmetic
                        $existingProduct = Product::lockForUpdate()->findOrFail($product['id']);

                        // GUARD 4: only treat item_id as an existing record if it's a valid positive int
                        $itemId          = isset($product['item_id']) && (int)$product['item_id'] > 0
                                           ? (int)$product['item_id']
                                           : null;
                        $oldPurchaseItem = $itemId ? PurchaseItem::find($itemId) : null;
                        $oldQuantity     = $oldPurchaseItem ? (int)$oldPurchaseItem->quantity : 0;

                        // GUARD 3: cap decrement so stock never goes negative
                        // (stock may have been reduced by sales since this purchase was created)
                        $safeDecrement = min($oldQuantity, (int)$existingProduct->quantity);
                        if ($safeDecrement > 0) {
                            $existingProduct->decrement('quantity', $safeDecrement);
                        }

                        // upsert: update existing item row if found, create new one otherwise
                        PurchaseItem::updateOrCreate(
                            ['id' => $itemId ?? 0],   // 0 never matches a real PK → always create when null
                            [
                                'purchase_id'    => $purchase->id,
                                'product_id'     => $product['id'],
                                'purchase_price' => $product['purchase_price'],
                                'price'          => $product['price'],
                                'quantity'       => $product['qty'],
                            ]
                        );

                        // Add the new quantity back atomically
                        $existingProduct->increment('quantity', $product['qty']);
                    }
                }

                return $purchase;
            });

            return response()->json([
                'message'  => 'Purchase saved successfully.',
                'purchase' => $purchase,
            ], 201);

        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show(Request $request, $id)
    {
        if ($request->wantsJson()) {
            $purchase = Purchase::with('items', 'supplier')->findOrFail($id);
            return $purchase;
        }
    }

    public function edit($id)
    {
        abort_if(!auth()->user()->can('purchase_update'), 403);
    }

    public function update(Request $request, Purchase $purchase)
    {
        abort_if(!auth()->user()->can('purchase_update'), 403);
    }

    public function destroy(Purchase $purchase)
    {
        abort_if(!auth()->user()->can('purchase_delete'), 403);
    }

    public function purchaseProducts(Request $request, $id)
    {
        $purchase = Purchase::with('items.product')->findOrFail($id);
        return view('backend.purchase.products', compact('id', 'purchase'));
    }
}
