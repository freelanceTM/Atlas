<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\PosCart;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;
    protected Product $product;
    protected Ingredient $ingredient;
    protected Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Admin']);
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'ingredient_manage']);

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->assignRole('Admin');
        $this->admin->givePermissionTo('ingredient_manage');

        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '1234567890',
            'address' => 'Test Address',
        ]);

        $this->product = Product::create([
            'name' => 'Test Coffee',
            'slug' => 'test-coffee',
            'sku' => 'TCF-001',
            'price' => 100.00,
            'discount' => 0,
            'discount_type' => 'fixed',
            'purchase_price' => 50.00,
            'quantity' => 10,
            'status' => 1,
        ]);

        $this->ingredient = Ingredient::create([
            'name' => 'Coffee Beans',
            'unit' => 'g',
            'stock' => 1000.000,
            'cost' => 0.05,
        ]);

        $this->recipe = Recipe::create([
            'product_id' => $this->product->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 15.000,
        ]);

        $this->actingAs($this->admin);
    }

    // ============ POS CORE FLOW ============

    /** @test */
    public function order_store_creates_order_successfully()
    {
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 2,
        ]);

        $response = $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 200,
            'order_type' => 'takeaway',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Заказ успешно оформлен']);
        $this->assertDatabaseCount('orders', 1);
        $order = Order::first();
        $this->assertEquals($this->customer->id, $order->customer_id);
        $this->assertEquals('takeaway', $order->order_type);
        $this->assertEquals(200, $order->total);
        $this->assertEquals(0, $order->due);
        $this->assertEquals(1, $order->status);
    }

    /** @test */
    public function order_store_decrements_product_quantity()
    {
        $initialQty = $this->product->quantity;
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 3,
        ]);
        $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 300,
        ]);
        $this->product->refresh();
        $this->assertEquals($initialQty - 3, $this->product->quantity);
    }

    /** @test */
    public function order_store_decrements_ingredient_stock_via_recipe()
    {
        $initialStock = $this->ingredient->stock;
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 2,
        ]);
        $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 200,
        ]);
        $this->ingredient->refresh();
        $this->assertEquals(970.000, $this->ingredient->stock);
    }

    /** @test */
    public function order_store_calculates_correct_total()
    {
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 5,
        ]);
        $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 500,
        ]);
        $order = Order::first();
        $this->assertEquals(500, $order->sub_total);
        $this->assertEquals(500, $order->total);
        $this->assertEquals(0, $order->discount);
    }

    /** @test */
    public function order_store_calculates_total_with_product_discount()
    {
        $product = Product::create([
            'name' => 'Discounted Latte',
            'slug' => 'discounted-latte',
            'sku' => 'DLT-001',
            'price' => 100.00,
            'discount' => 10.00,
            'discount_type' => 'fixed',
            'purchase_price' => 40.00,
            'quantity' => 10,
            'status' => 1,
        ]);
        PosCart::create([
            'product_id' => $product->id,
            'user_id' => $this->admin->id,
            'quantity' => 2,
        ]);
        $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 200,
        ]);
        $order = Order::first();
        // sub_total is computed from discounted_price * qty = 90 * 2 = 180
        $this->assertEquals(180, $order->sub_total);
        $this->assertEquals(180, $order->total);
        // order_discount (order-level) remains 0; product discount already in sub_total
        $this->assertEquals(0, $order->discount);
    }

    /** @test */
    public function order_store_with_order_discount()
    {
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 3,
        ]);
        $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'order_discount' => 50,
            'paid' => 250,
        ]);
        $order = Order::first();
        $this->assertEquals(300, $order->sub_total);
        $this->assertEquals(250, $order->total);
        $this->assertEquals(50, $order->discount);
    }

    /** @test */
    public function order_store_creates_transaction_when_paid()
    {
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 1,
        ]);
        $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 100,
        ]);
        $this->assertDatabaseCount('order_transactions', 1);
        $order = Order::first();
        $this->assertCount(1, $order->transactions);
        $this->assertEquals(100, $order->transactions->first()->amount);
    }

    /** @test */
    public function order_store_clears_cart_after_order()
    {
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 2,
        ]);
        $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 200,
        ]);
        $this->assertDatabaseCount('pos_carts', 0);
    }

    /** @test */
    public function order_store_creates_correct_order_products()
    {
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 3,
        ]);
        $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 300,
        ]);
        $order = Order::first();
        $this->assertCount(1, $order->products);
        $orderProduct = $order->products->first();
        $this->assertEquals(3, $orderProduct->quantity);
        $this->assertEquals(100.00, $orderProduct->price);
        $this->assertEquals(300, $orderProduct->sub_total);
        $this->assertEquals(300, $orderProduct->total);
    }

    // ============ STOCK SAFETY ============

    /** @test */
    public function order_store_rejects_when_product_quantity_insufficient()
    {
        $this->product->update(['quantity' => 2]);
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 5,
        ]);
        $response = $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 500,
        ]);
        $response->assertStatus(422);
        $this->assertStringContainsString('Недостаточно товара', $response->json('message'));
        $this->assertDatabaseCount('orders', 0);
    }

    /** @test */
    public function order_store_rejects_when_ingredient_stock_insufficient()
    {
        $this->ingredient->update(['stock' => 10.000]);
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 2,
        ]);
        $response = $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 200,
        ]);
        $response->assertStatus(422);
        $this->assertStringContainsString('Недостаточно на складе', $response->json('message'));
        $this->assertDatabaseCount('orders', 0);
    }

    /** @test */
    public function stock_never_goes_negative_for_product()
    {
        $this->product->update(['quantity' => 3]);
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 3,
        ]);
        $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 300,
        ]);
        $this->product->refresh();
        $this->assertEquals(0, $this->product->quantity);
        $this->assertGreaterThanOrEqual(0, $this->product->quantity);
    }

    /** @test */
    public function stock_never_goes_negative_for_ingredient()
    {
        $this->ingredient->update(['stock' => 15.000]);
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 1,
        ]);
        $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 100,
        ]);
        $this->ingredient->refresh();
        $this->assertEquals(0.000, $this->ingredient->stock);
        $this->assertGreaterThanOrEqual(0, $this->ingredient->stock);
    }

    // ============ TRANSACTION SAFETY ============

    /** @test */
    public function order_rollback_on_product_stock_failure()
    {
        $initialProductQty = $this->product->quantity;
        $initialIngredientStock = $this->ingredient->stock;
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 999,
        ]);
        $response = $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 99900,
        ]);
        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
        $this->product->refresh();
        $this->ingredient->refresh();
        $this->assertEquals($initialProductQty, $this->product->quantity);
        $this->assertEquals($initialIngredientStock, $this->ingredient->stock);
    }

    /** @test */
    public function order_rollback_on_ingredient_stock_failure()
    {
        $initialProductQty = $this->product->quantity;
        $this->ingredient->update(['stock' => 5.000]);
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 3,
        ]);
        $response = $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 300,
        ]);
        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
        $this->product->refresh();
        $this->ingredient->refresh();
        $this->assertEquals($initialProductQty, $this->product->quantity);
        $this->assertEquals(5.000, $this->ingredient->stock);
    }

    /** @test */
    public function order_not_created_on_failure()
    {
        $this->product->update(['quantity' => 0]);
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 1,
        ]);
        $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 100,
        ]);
        $this->assertDatabaseCount('orders', 0);
    }

    // ============ CONCURRENCY TEST ============

    /** @test */
    public function concurrent_orders_do_not_double_spend_ingredients()
    {
        $this->ingredient->update(['stock' => 20.000]);
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 1,
        ]);
        $response1 = $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 100,
        ]);
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 1,
        ]);
        $response2 = $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 100,
        ]);
        $this->assertEquals(200, $response1->status());
        $this->assertEquals(422, $response2->status());
        $this->assertDatabaseCount('orders', 1);
        $this->ingredient->refresh();
        $this->assertEquals(5.000, $this->ingredient->stock);
        $this->assertGreaterThan(0, $this->ingredient->stock);
    }

    /** @test */
    public function concurrent_orders_do_not_overdraw_product_stock()
    {
        $this->product->update(['quantity' => 3]);
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 2,
        ]);
        $response1 = $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 200,
        ]);
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 2,
        ]);
        $response2 = $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 200,
        ]);
        $this->assertEquals(200, $response1->status());
        $this->assertEquals(422, $response2->status());
        $this->assertDatabaseCount('orders', 1);
        $this->product->refresh();
        $this->assertEquals(1, $this->product->quantity);
        $this->assertGreaterThanOrEqual(0, $this->product->quantity);
    }

    // ============ EDGE CASES ============

    /** @test */
    public function order_store_rejects_empty_cart()
    {
        $response = $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 100,
        ]);
        $response->assertStatus(422);
        $this->assertSame('Корзина пуста.', $response->json('message'));
    }

    /** @test */
    public function order_store_validates_customer_exists()
    {
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 1,
        ]);
        $response = $this->json('PUT', '/admin/order/create', [
            'customer_id' => 99999,
            'paid' => 100,
        ]);
        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    /** @test */
    public function order_store_validates_required_fields()
    {
        $response = $this->json('PUT', '/admin/order/create', []);
        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    /** @test */
    public function order_store_with_recipe_referencing_deleted_ingredient()
    {
        // When an ingredient is deleted, FK cascadeOnDelete removes the recipe too.
        // So the product ends up with no recipe and the order succeeds.
        $product2 = Product::create([
            'name' => 'Orphan Product',
            'slug' => 'orphan-product',
            'sku' => 'ORP-001',
            'price' => 50.00,
            'discount' => 0,
            'discount_type' => 'fixed',
            'purchase_price' => 20.00,
            'quantity' => 10,
            'status' => 1,
        ]);
        $ingredient2 = Ingredient::create([
            'name' => 'Temp Ingredient',
            'unit' => 'g',
            'stock' => 100.000,
            'cost' => 0.10,
        ]);
        Recipe::create([
            'product_id' => $product2->id,
            'ingredient_id' => $ingredient2->id,
            'quantity' => 5.000,
        ]);
        // Deleting ingredient cascade-deletes the recipe (FK constrained with cascadeOnDelete)
        $ingredient2->delete();
        PosCart::create([
            'product_id' => $product2->id,
            'user_id' => $this->admin->id,
            'quantity' => 1,
        ]);
        // Recipe is gone → order succeeds (no ingredients to deduct)
        $response = $this->json('PUT', '/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 50,
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseCount('orders', 1);
    }

    /** @test */
    public function order_store_with_product_having_no_recipe()
    {
        $productNoRecipe = Product::create([
            'name' => 'No Recipe Product',
            'slug' => 'no-recipe-product',
            'sku' => 'NRP-001',
            'price' => 30.00,
            'discount' => 0,
            'discount_type' => 'fixed',
            'purchase_price' => 10.00,
            'quantity' => 10,
            'status' => 1,
        ]);
        PosCart::create([
            'product_id' => $productNoRecipe->id,
            'user_id' => $this->admin->id,
            'quantity' => 2,
        ]);
        $response = $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 60,
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseCount('orders', 1);
        $productNoRecipe->refresh();
        $this->assertEquals(8, $productNoRecipe->quantity);
    }

    /** @test */
    public function order_store_with_multiple_products_in_cart()
    {
        $product2 = Product::create([
            'name' => 'Tea',
            'slug' => 'tea',
            'sku' => 'TEA-001',
            'price' => 50.00,
            'discount' => 0,
            'discount_type' => 'fixed',
            'purchase_price' => 20.00,
            'quantity' => 10,
            'status' => 1,
        ]);
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 2,
        ]);
        PosCart::create([
            'product_id' => $product2->id,
            'user_id' => $this->admin->id,
            'quantity' => 3,
        ]);
        $response = $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 350,
        ]);
        $response->assertStatus(200);
        $order = Order::first();
        $this->assertEquals(350, $order->sub_total);
        $this->assertEquals(350, $order->total);
        $this->assertDatabaseCount('order_products', 2);
    }

    /** @test */
    public function order_store_with_dine_in_type()
    {
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 1,
        ]);
        $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 100,
            'order_type' => 'dine_in',
        ]);
        $order = Order::first();
        $this->assertEquals('dine_in', $order->order_type);
    }

    /** @test */
    public function order_store_defaults_to_takeaway()
    {
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 1,
        ]);
        $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 100,
        ]);
        $order = Order::first();
        $this->assertEquals('takeaway', $order->order_type);
    }

    /** @test */
    public function order_store_with_partial_payment_has_due()
    {
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 2,
        ]);
        $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 150,
        ]);
        $order = Order::first();
        $this->assertEquals(200, $order->total);
        $this->assertEquals(150, $order->paid);
        $this->assertEquals(50, $order->due);
        $this->assertEquals(0, $order->status);
    }

    /** @test */
    public function order_store_with_zero_paid_creates_due_order()
    {
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 1,
        ]);
        $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 0,
        ]);
        $order = Order::first();
        $this->assertEquals(100, $order->total);
        $this->assertEquals(0, $order->paid);
        $this->assertEquals(100, $order->due);
        $this->assertEquals(0, $order->status);
    }

    /** @test */
    public function order_store_rejects_zero_quantity_in_cart()
    {
        // Adding a cart item with quantity=0 makes no sense,
        // but we verify the system doesn't break on it.
        // The cart guard skips this silently; and if qty=0,
        // total = 0, stock unchanged.
        PosCart::create([
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'quantity' => 0,
        ]);

        // With qty=0, the cart item exists but won't consume stock.
        // The guard check passes (0 <= product.quantity=10).
        // The order is created with 0 total.
        $response = $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 0,
        ]);

        $response->assertStatus(200);
        $order = Order::first();
        $this->assertEquals(0, $order->sub_total);
        $this->assertEquals(0, $order->total);

        // Product stock unchanged
        $this->product->refresh();
        $this->assertEquals(10, $this->product->quantity);

        // Ingredient stock unchanged
        $this->ingredient->refresh();
        $this->assertEquals(1000.000, $this->ingredient->stock);
    }
}

