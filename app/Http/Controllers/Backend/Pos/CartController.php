<?php

namespace App\Http\Controllers\Backend\Pos;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\PosCart;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        if ($request->wantsJson()) {
            $cartItems = PosCart::where("user_id", auth()->id())
                ->with("product")
                ->latest("created_at")
                ->get()
                ->map(function ($item) {
                    $item->row_total = round(($item->quantity * $item->product->discounted_price), 2);
                    return $item;
                });
            $total = $cartItems->sum("row_total");
            return response()->json([
                "carts" => $cartItems,
                "total" => round($total, 2),
            ]);
        }
        PosCart::where("user_id", auth()->id())->delete();
        return view("backend.cart.index");
    }

    public function getProducts(Request $request)
    {
        $products = Product::query()->active()->stocked();
        $products->when($request->search, function ($query, $search) {
            $query->where("name", "LIKE", "%{$search}%");
        });
        $products->when($request->barcode, function ($query, $barcode) {
            $query->where("sku", $barcode);
        });
        $products = $products->latest()->paginate(96);
        if (request()->wantsJson()) {
            return ProductResource::collection($products);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            "id" => "required|exists:products,id",
        ]);

        $product_id = $request->id;
        $product    = Product::find($product_id);

        if (!$product->status) {
            return response()->json(["message" => "Product is not available"], 400);
        }
        if ($product->quantity <= 0) {
            return response()->json(["message" => "Insufficient stock available"], 400);
        }

        $cartItem = PosCart::where("user_id", auth()->id())
            ->where("product_id", $product_id)
            ->first();

        if ($cartItem) {
            if ($cartItem->quantity < $product->quantity) {
                $cartItem->quantity += 1;
                $cartItem->save();
                return response()->json(["message" => "Quantity updated", "quantity" => $cartItem->quantity], 200);
            } else {
                return response()->json(["message" => "Cannot add more, stock limit reached"], 400);
            }
        } else {
            $cart             = new PosCart();
            $cart->user_id    = auth()->id();
            $cart->product_id = $product_id;
            $cart->quantity   = 1;
            $cart->save();
            return response()->json(["message" => "Product added to cart", "quantity" => 1], 201);
        }
    }

    /**
     * FIX IDOR: Убедиться что корзина принадлежит текущему пользователю.
     * Риск: без проверки user_id кассир A мог изменить корзину кассира B.
     */
    public function increment(Request $request)
    {
        $request->validate([
            "id" => "required|integer|exists:pos_carts,id",
        ]);

        // IDOR fix: scope to current user
        $cart = PosCart::where("id", $request->id)
            ->where("user_id", auth()->id())
            ->with("product")
            ->first();

        if (!$cart) {
            return response()->json(["message" => "Cart item not found."], 404);
        }
        if ($cart->product->quantity <= 0) {
            return response()->json(["message" => "Insufficient stock available"], 400);
        }
        if ($cart->quantity == $cart->product->quantity) {
            return response()->json(["message" => "Cannot add more, stock limit reached"], 400);
        }
        $cart->quantity = $cart->quantity + 1;
        $cart->save();
        return response()->json(["message" => "Cart Updated successfully"], 200);
    }

    public function decrement(Request $request)
    {
        $request->validate([
            "id" => "required|integer|exists:pos_carts,id",
        ]);

        // IDOR fix: scope to current user
        $cart = PosCart::where("id", $request->id)
            ->where("user_id", auth()->id())
            ->first();

        if (!$cart) {
            return response()->json(["message" => "Cart item not found."], 404);
        }
        if ($cart->quantity <= 1) {
            return response()->json(["message" => "Quantity cannot be less than 1."], 400);
        }
        $cart->quantity = $cart->quantity - 1;
        $cart->save();
        return response()->json(["message" => "Cart Updated successfully"], 200);
    }

    public function delete(Request $request)
    {
        $request->validate([
            "id" => "required|integer|exists:pos_carts,id",
        ]);

        // IDOR fix: scope to current user
        $cart = PosCart::where("id", $request->id)
            ->where("user_id", auth()->id())
            ->first();

        if (!$cart) {
            return response()->json(["message" => "Cart item not found."], 404);
        }
        $cart->delete();
        return response()->json(["message" => "Item successfully deleted"], 200);
    }

    public function empty()
    {
        $deletedCount = PosCart::where("user_id", auth()->id())->delete();
        if ($deletedCount > 0) {
            return response()->json(["message" => "Cart successfully cleared"], 200);
        }
        return response()->json(["message" => "Cart is already empty"], 204);
    }
}
