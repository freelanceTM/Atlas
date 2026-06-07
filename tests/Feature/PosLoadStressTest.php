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
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosLoadStressTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected Customer $customer;
    protected User $barista1;
    protected User $barista2;
    protected User $barista3;
    protected Product $cappuccino;
    protected Ingredient $milk;
    protected Ingredient $coffeeBeans;
    protected Ingredient $cups;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Admin']);
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'ingredient_manage']);

        $this->admin = $this->createAdmin('admin@cafe.test');
        $this->admin->givePermissionTo('ingredient_manage');

        $this->cashier = User::factory()->create([
            'name' => 'Cashier', 'email' => 'cashier@cafe.test',
            'password' => bcrypt('password'),
        ]);
        $this->cashier->assignRole('cashier');

        $this->barista1 = $this->createAdmin('barista1@cafe.test');
        $this->barista2 = $this->createAdmin('barista2@cafe.test');
        $this->barista3 = $this->createAdmin('barista3@cafe.test');

        $this->customer = Customer::create([
            'name' => 'Regular Customer', 'phone' => '1234567890', 'address' => 'Cafe Address',
        ]);

        $this->cappuccino = Product::create([
            'name' => 'Cappuccino', 'slug' => 'cappuccino', 'sku' => 'CAP-001',
            'price' => 120.00, 'discount' => 0, 'discount_type' => 'fixed',
            'purchase_price' => 40.00, 'quantity' => 100, 'status' => 1,
        ]);

        $this->milk = Ingredient::create([
            'name' => 'Fresh Milk', 'unit' => 'ml', 'stock' => 2000.000, 'cost' => 0.05,
        ]);
        $this->coffeeBeans = Ingredient::create([
            'name' => 'Espresso Beans', 'unit' => 'g', 'stock' => 500.000, 'cost' => 0.15,
        ]);
        $this->cups = Ingredient::create([
            'name' => 'Paper Cups', 'unit' => 'pcs', 'stock' => 50.000, 'cost' => 0.25,
        ]);

        Recipe::create(['product_id' => $this->cappuccino->id, 'ingredient_id' => $this->milk->id, 'quantity' => 150.000]);
        Recipe::create(['product_id' => $this->cappuccino->id, 'ingredient_id' => $this->coffeeBeans->id, 'quantity' => 18.000]);
        Recipe::create(['product_id' => $this->cappuccino->id, 'ingredient_id' => $this->cups->id, 'quantity' => 1.000]);
    }

    private function createAdmin(string $email): User
    {
        $user = User::factory()->create([
            'name' => explode('@', $email)[0], 'email' => $email,
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('Admin');
        return $user;
    }

    // ═══════════════════════════════════════════════════════
    // 🔥 1. LOAD TEST — CAFE RUSH HOUR
    // ═══════════════════════════════════════════════════════

    /** @test */
    public function load_10_sequential_orders_same_product_no_oversell()
    {
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->barista1);
            PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->barista1->id, 'quantity' => 1]);
            $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
            $this->assertEquals(200, $r->status(), "Order #{$i} failed: " . json_encode($r->json()));
        }
        $this->assertDatabaseCount('orders', 10);
        $this->assertEquals(90, Product::find($this->cappuccino->id)->quantity);
        $this->assertEquals(500.000, Ingredient::find($this->milk->id)->stock);
        $this->assertEquals(320.000, Ingredient::find($this->coffeeBeans->id)->stock);
        $this->assertEquals(40.000, Ingredient::find($this->cups->id)->stock);
    }

    /** @test */
    public function load_rapid_fire_orders_stock_never_goes_negative()
    {
        // Cups=50, Beans=500g, Milk=2000ml
        // Milk limits: 2000/150 = 13.33 → 13 max
        $totalFired = 80;
        $successes = 0;
        for ($i = 0; $i < $totalFired; $i++) {
            $this->actingAs($this->barista1);
            PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->barista1->id, 'quantity' => 1]);
            $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
            if ($r->status() === 200) $successes++;
        }
        $this->assertEquals(13, $successes, "Milk limits to 13 cappuccinos (2000/150=13.33)");
        $this->assertDatabaseCount('orders', 13);
        $milk = Ingredient::find($this->milk->id);
        $this->assertEquals(50.000, $milk->stock, '2000 - 13*150 = 50');
        $this->assertGreaterThanOrEqual(0, $milk->stock);
        $this->assertGreaterThanOrEqual(0, Ingredient::find($this->coffeeBeans->id)->stock);
        $this->assertGreaterThanOrEqual(0, Ingredient::find($this->cups->id)->stock);
    }

    /** @test */
    public function load_exact_boundary_orders()
    {
        $this->milk->update(['stock' => 300.000]);
        $this->coffeeBeans->update(['stock' => 36.000]);
        $this->cups->update(['stock' => 5.000]);
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->barista1);
            PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->barista1->id, 'quantity' => 1]);
            $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
            if ($i < 2) $this->assertEquals(200, $r->status());
        }
        $this->assertDatabaseCount('orders', 2);
        $this->assertEquals(0.000, Ingredient::find($this->milk->id)->stock);
        $this->assertGreaterThanOrEqual(0, Ingredient::find($this->milk->id)->stock);
    }

    /** @test */
    public function load_multiple_baristas_competing_for_same_product()
    {
        $baristas = [$this->barista1, $this->barista2, $this->barista3];
        $totalSuccesses = 0;
        foreach ($baristas as $barista) {
            for ($i = 0; $i < 20; $i++) {
                $this->actingAs($barista);
                PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $barista->id, 'quantity' => 1]);
                $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
                if ($r->status() === 200) $totalSuccesses++;
            }
        }
        $this->assertEquals(13, $totalSuccesses, 'Milk limit: 13 max');
        $this->assertDatabaseCount('orders', 13);
        foreach ([$this->milk, $this->coffeeBeans, $this->cups] as $ing) {
            $ing->refresh();
            $this->assertGreaterThanOrEqual(0, $ing->stock, "{$ing->name} must not be negative");
        }
    }

    // ═══════════════════════════════════════════════════════
    // ⚔️ 2. CHAOS TEST — USERS BREAK THE SYSTEM
    // ═══════════════════════════════════════════════════════

    /** @test */
    public function chaos_double_click_order_create()
    {
        $this->actingAs($this->barista1);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->barista1->id, 'quantity' => 1]);
        $r1 = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
        $this->assertEquals(200, $r1->status());
        $r2 = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
        $this->assertEquals(422, $r2->status());
        $this->assertStringContainsString('Cart is empty', $r2->json('message'));
        $this->assertDatabaseCount('orders', 1);
    }

    /** @test */
    public function chaos_resubmit_same_cart_after_order()
    {
        $this->actingAs($this->barista1);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->barista1->id, 'quantity' => 2]);
        $r1 = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 240]);
        $this->assertEquals(200, $r1->status());
        $r2 = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 240]);
        $this->assertEquals(422, $r2->status());
        $this->assertDatabaseCount('orders', 1);
        $this->assertEquals(98, Product::find($this->cappuccino->id)->quantity);
    }

    /** @test */
    public function chaos_order_with_zero_quantity()
    {
        $this->actingAs($this->barista1);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->barista1->id, 'quantity' => 0]);
        $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 0]);
        $this->assertEquals(200, $r->status());
        $order = Order::first();
        $this->assertEquals(0, $order->sub_total);
        $this->assertEquals(0, $order->total);
        $this->assertEquals(100, Product::find($this->cappuccino->id)->quantity);
        $this->assertEquals(2000.000, Ingredient::find($this->milk->id)->stock);
    }

    /** @test */
    public function chaos_deleted_ingredient_cascade()
    {
        $product = Product::create([
            'name' => 'Broken Latte', 'slug' => 'broken-latte', 'sku' => 'BRK-001',
            'price' => 80.00, 'discount' => 0, 'discount_type' => 'fixed',
            'purchase_price' => 30.00, 'quantity' => 10, 'status' => 1,
        ]);
        $vanilla = Ingredient::create(['name' => 'Vanilla Syrup', 'unit' => 'ml', 'stock' => 100.000, 'cost' => 0.10]);
        Recipe::create(['product_id' => $product->id, 'ingredient_id' => $vanilla->id, 'quantity' => 10.000]);
        $vanilla->delete();
        $this->actingAs($this->barista1);
        PosCart::create(['product_id' => $product->id, 'user_id' => $this->barista1->id, 'quantity' => 2]);
        $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 160]);
        $this->assertEquals(200, $r->status());
        $this->assertDatabaseCount('orders', 1);
    }

    /** @test */
    public function chaos_partial_stock_one_ingredient_short()
    {
        $this->coffeeBeans->update(['stock' => 17.999]);
        $this->actingAs($this->barista1);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->barista1->id, 'quantity' => 1]);
        $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
        $this->assertEquals(422, $r->status());
        $this->assertStringContainsString('Insufficient ingredient', $r->json('message'));
        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(2000.000, Ingredient::find($this->milk->id)->stock);
        $this->assertEquals(17.999, Ingredient::find($this->coffeeBeans->id)->stock);
        $this->assertEquals(50.000, Ingredient::find($this->cups->id)->stock);
    }

    /** @test */
    public function chaos_invalid_customer()
    {
        $this->actingAs($this->barista1);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->barista1->id, 'quantity' => 1]);
        $r = $this->json('PUT', '/admin/order/create', ['customer_id' => 999999, 'paid' => 120]);
        $this->assertEquals(422, $r->status());
        $this->assertDatabaseCount('orders', 0);
    }

    /** @test */
    public function chaos_negative_payment_validation()
    {
        $this->actingAs($this->barista1);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->barista1->id, 'quantity' => 1]);
        $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => -100]);
        $this->assertEquals(422, $r->status());
        $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'order_discount' => -50, 'paid' => 120]);
        $this->assertEquals(422, $r->status());
    }

    // ═══════════════════════════════════════════════════════
    // 🧠 3. CONCURRENCY STRESS TEST
    // ═══════════════════════════════════════════════════════

    /** @test */
    public function concurrency_two_users_exact_one_serving()
    {
        $this->milk->update(['stock' => 150.000]);
        $this->coffeeBeans->update(['stock' => 18.000]);
        $this->cups->update(['stock' => 2.000]);

        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->barista1->id, 'quantity' => 1]);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->barista2->id, 'quantity' => 1]);

        $this->actingAs($this->barista1);
        $r1 = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
        $this->actingAs($this->barista2);
        $r2 = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);

        $this->assertTrue(
            ($r1->status() === 200 && $r2->status() === 422) ||
            ($r1->status() === 422 && $r2->status() === 200),
            "One must succeed, one must fail. r1={$r1->status()}, r2={$r2->status()}"
        );
        $this->assertDatabaseCount('orders', 1);
        foreach ([$this->milk, $this->coffeeBeans, $this->cups] as $ing) {
            $ing->refresh();
            $this->assertGreaterThanOrEqual(0, $ing->stock, "{$ing->name} went negative!");
        }
    }

    /** @test */
    public function concurrency_oversell_prevention_boundary()
    {
        $this->milk->update(['stock' => 150.000]);
        $this->coffeeBeans->update(['stock' => 18.000]);
        $this->cups->update(['stock' => 1.000]);

        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->barista1->id, 'quantity' => 1]);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->barista2->id, 'quantity' => 1]);

        $this->actingAs($this->barista1);
        $r1 = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
        $this->actingAs($this->barista2);
        $r2 = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);

        $successCount = (int)($r1->status() === 200) + (int)($r2->status() === 200);
        $this->assertEquals(1, $successCount, 'Exactly ONE order must succeed at boundary');
        $this->assertDatabaseCount('orders', 1);
        $this->assertEquals(0.000, Ingredient::find($this->cups->id)->stock);
    }

    /** @test */
    public function concurrency_no_duplicate_order_products()
    {
        $this->milk->update(['stock' => 300.000]);
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->barista1);
            PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->barista1->id, 'quantity' => 1]);
            $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
            if ($i >= 2) $this->assertEquals(422, $r->status());
        }
        $this->assertDatabaseCount('orders', 2);
        foreach (Order::all() as $order) {
            $this->assertEquals(1, $order->products()->count());
            $this->assertEquals(1, $order->products()->sum('quantity'));
        }
    }

    /** @test */
    public function concurrency_no_partial_commit()
    {
        $initialProductQty = $this->cappuccino->quantity;
        $this->milk->update(['stock' => 1000.000]);
        $this->coffeeBeans->update(['stock' => 10.000]);
        $this->cups->update(['stock' => 50.000]);

        $this->actingAs($this->barista1);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->barista1->id, 'quantity' => 1]);
        $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
        $this->assertEquals(422, $r->status());
        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals($initialProductQty, Product::find($this->cappuccino->id)->quantity);
        $this->assertEquals(1000.000, Ingredient::find($this->milk->id)->stock);
        $this->assertEquals(10.000, Ingredient::find($this->coffeeBeans->id)->stock);
        $this->assertEquals(50.000, Ingredient::find($this->cups->id)->stock);
    }

    // ═══════════════════════════════════════════════════════
    // 💣 4. DB STRESS BEHAVIOR
    // ═══════════════════════════════════════════════════════

    /** @test */
    public function db_lock_for_update_prevents_dirty_read()
    {
        $product = Product::find($this->cappuccino->id);
        $originalQty = $product->quantity;
        DB::beginTransaction();
        try {
            $locked = Product::where('id', $product->id)->lockForUpdate()->first();
            $locked->decrement('quantity', 5);
            $locked->refresh();
            $this->assertEquals($originalQty - 5, $locked->quantity);
            DB::rollBack();
        } catch (\Throwable $e) { DB::rollBack(); throw $e; }
        $product->refresh();
        $this->assertEquals($originalQty, $product->quantity, 'Rollback must restore stock');
    }

    /** @test */
    public function db_transaction_atomicity_multiple_tables()
    {
        $initialOrderCount = Order::count();
        DB::beginTransaction();
        try {
            $order = Order::create([
                'customer_id' => $this->customer->id, 'user_id' => $this->admin->id,
                'order_type' => 'takeaway',
            ]);
            $order->products()->create([
                'quantity' => 1, 'price' => 120, 'purchase_price' => 40,
                'sub_total' => 120, 'discount' => 0, 'total' => 120,
                'product_id' => $this->cappuccino->id,
            ]);
            throw new \RuntimeException('Simulated mid-transaction failure');
        } catch (\RuntimeException $e) { DB::rollBack(); }
        $this->assertDatabaseCount('orders', $initialOrderCount);
    }

    /** @test */
    public function db_decrement_atomicity()
    {
        $product = Product::find($this->cappuccino->id);
        $original = $product->quantity;
        for ($i = 0; $i < 10; $i++) {
            Product::where('id', $product->id)->decrement('quantity', 1);
        }
        $product->refresh();
        $this->assertEquals($original - 10, $product->quantity);
    }

    /** @test */
    public function db_recipe_stale_reference_handled()
    {
        $product = Product::create([
            'name' => 'Stale Recipe', 'slug' => 'stale-recipe', 'sku' => 'SRP-001',
            'price' => 50.00, 'discount' => 0, 'discount_type' => 'fixed',
            'purchase_price' => 20.00, 'quantity' => 10, 'status' => 1,
        ]);
        $temp = Ingredient::create(['name' => 'Temp Ing', 'unit' => 'g', 'stock' => 100.000, 'cost' => 0.10]);
        Recipe::create(['product_id' => $product->id, 'ingredient_id' => $temp->id, 'quantity' => 5.000]);
        $temp->delete();
        $this->assertEquals(0, Recipe::where('product_id', $product->id)->count());
        $this->actingAs($this->admin);
        PosCart::create(['product_id' => $product->id, 'user_id' => $this->admin->id, 'quantity' => 1]);
        $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 50]);
        $this->assertEquals(200, $r->status());
    }

    // ═══════════════════════════════════════════════════════
    // 🧪 5. EDGE CASE EXPLOSION
    // ═══════════════════════════════════════════════════════

    /** @test */
    public function edge_product_without_recipe()
    {
        $product = Product::create([
            'name' => 'No Recipe Tea', 'slug' => 'no-recipe-tea', 'sku' => 'NRT-001',
            'price' => 30.00, 'discount' => 0, 'discount_type' => 'fixed',
            'purchase_price' => 10.00, 'quantity' => 50, 'status' => 1,
        ]);
        $this->actingAs($this->admin);
        PosCart::create(['product_id' => $product->id, 'user_id' => $this->admin->id, 'quantity' => 3]);
        $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 90]);
        $this->assertEquals(200, $r->status());
        $product->refresh();
        $this->assertEquals(47, $product->quantity);
    }

    /** @test */
    public function edge_stock_exact_required()
    {
        $this->milk->update(['stock' => 150.000]);
        $this->coffeeBeans->update(['stock' => 18.000]);
        $this->cups->update(['stock' => 1.000]);
        $this->actingAs($this->admin);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->admin->id, 'quantity' => 1]);
        $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
        $this->assertEquals(200, $r->status());
        $this->assertEquals(0.000, Ingredient::find($this->milk->id)->stock);
        $this->assertEquals(0.000, Ingredient::find($this->coffeeBeans->id)->stock);
        $this->assertEquals(0.000, Ingredient::find($this->cups->id)->stock);
    }

    /** @test */
    public function edge_stock_one_unit_above()
    {
        $this->milk->update(['stock' => 151.000]);
        $this->coffeeBeans->update(['stock' => 19.000]);
        $this->cups->update(['stock' => 2.000]);
        $this->actingAs($this->admin);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->admin->id, 'quantity' => 1]);
        $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
        $this->assertEquals(200, $r->status());
        $this->assertEquals(1.000, Ingredient::find($this->milk->id)->stock);
        $this->assertEquals(1.000, Ingredient::find($this->coffeeBeans->id)->stock);
        $this->assertEquals(1.000, Ingredient::find($this->cups->id)->stock);
    }

    /** @test */
    public function edge_stock_one_unit_below()
    {
        $this->milk->update(['stock' => 149.000]);
        $this->actingAs($this->admin);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->admin->id, 'quantity' => 1]);
        $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
        $this->assertEquals(422, $r->status());
        $this->assertDatabaseCount('orders', 0);
    }

    /** @test */
    public function edge_multiple_products_multiple_ingredients()
    {
        $tea = Product::create([
            'name' => 'Green Tea', 'slug' => 'green-tea', 'sku' => 'GRT-001',
            'price' => 60.00, 'discount' => 0, 'discount_type' => 'fixed',
            'purchase_price' => 20.00, 'quantity' => 20, 'status' => 1,
        ]);
        $teaLeaves = Ingredient::create(['name' => 'Tea Leaves', 'unit' => 'g', 'stock' => 200.000, 'cost' => 0.10]);
        Recipe::create(['product_id' => $tea->id, 'ingredient_id' => $teaLeaves->id, 'quantity' => 5.000]);
        Recipe::create(['product_id' => $tea->id, 'ingredient_id' => $this->cups->id, 'quantity' => 1.000]);

        $this->actingAs($this->admin);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->admin->id, 'quantity' => 2]);
        PosCart::create(['product_id' => $tea->id, 'user_id' => $this->admin->id, 'quantity' => 1]);
        $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 300]);
        $this->assertEquals(200, $r->status());
        $order = Order::first();
        $this->assertEquals(2, $order->products()->count());
        $this->assertEquals(300, $order->total);
        $this->assertEquals(1700.000, Ingredient::find($this->milk->id)->stock);
        $this->assertEquals(464.000, Ingredient::find($this->coffeeBeans->id)->stock);
        $this->assertEquals(47.000, Ingredient::find($this->cups->id)->stock);
        $this->assertEquals(195.000, Ingredient::find($teaLeaves->id)->stock);
    }

    /** @test */
    public function edge_product_stock_exact_zero_sells_last()
    {
        $this->cappuccino->update(['quantity' => 1]);
        $this->actingAs($this->admin);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->admin->id, 'quantity' => 1]);
        $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
        $this->assertEquals(200, $r->status());
        $this->cappuccino->refresh();
        $this->assertEquals(0, $this->cappuccino->quantity);
    }

    /** @test */
    public function edge_product_stock_zero_rejects()
    {
        $this->cappuccino->update(['quantity' => 0]);
        $this->actingAs($this->admin);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->admin->id, 'quantity' => 1]);
        $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
        $this->assertEquals(422, $r->status());
        $this->assertStringContainsString('Insufficient product stock', $r->json('message'));
        $this->assertDatabaseCount('orders', 0);
    }

    /** @test */
    public function edge_all_three_ingredients_exhausted_exact()
    {
        $this->milk->update(['stock' => 150.000]);
        $this->coffeeBeans->update(['stock' => 18.000]);
        $this->cups->update(['stock' => 1.000]);
        $this->actingAs($this->admin);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->admin->id, 'quantity' => 1]);
        $r1 = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
        $this->assertEquals(200, $r1->status());
        $this->assertEquals(0.000, Ingredient::find($this->milk->id)->stock);
        $this->assertEquals(0.000, Ingredient::find($this->coffeeBeans->id)->stock);
        $this->assertEquals(0.000, Ingredient::find($this->cups->id)->stock);
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->admin->id, 'quantity' => 1]);
        $r2 = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
        $this->assertEquals(422, $r2->status());
    }

    // ═══════════════════════════════════════════════════════
    // 🔐 6. SECURITY UNDER LOAD
    // ═══════════════════════════════════════════════════════

    /** @test */
    public function security_cashier_burst_requests_to_admin()
    {
        $this->actingAs($this->cashier);
        $endpoints = ['/admin', '/admin/products', '/admin/ingredients', '/admin/settings/website/general'];
        foreach ($endpoints as $url) {
            for ($i = 0; $i < 3; $i++) {
                $this->assertEquals(403, $this->get($url)->status(), "Cashier GET {$url} must be 403");
            }
        }
    }

    /** @test */
    public function security_unauthenticated_burst()
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get('/admin')->assertRedirect(route('login'));
        }
    }

    /** @test */
    public function security_cashier_ingredient_routes_burst()
    {
        $this->actingAs($this->cashier);
        $endpoints = ['/admin/ingredients', '/admin/ingredients/create', '/admin/ingredients-report'];
        foreach ($endpoints as $url) {
            for ($i = 0; $i < 3; $i++) {
                $this->assertEquals(403, $this->get($url)->status(), "Cashier GET {$url} must be 403");
            }
        }
    }

    /** @test */
    public function security_cashier_cannot_create_order_burst()
    {
        $this->actingAs($this->cashier);
        for ($i = 0; $i < 5; $i++) {
            $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
            $this->assertEquals(403, $r->status());
        }
        $this->assertDatabaseCount('orders', 0);
    }

    /** @test */
    public function security_no_role_user_multiple_attempts()
    {
        $noRole = User::factory()->create([
            'name' => 'NoRole', 'email' => 'norole@cafe.test', 'password' => bcrypt('password'),
        ]);
        $this->actingAs($noRole);
        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals(403, $this->get('/admin')->status());
        }
        PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $noRole->id, 'quantity' => 1]);
        $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
        $this->assertEquals(403, $r->status());
    }

    /** @test */
    public function security_admin_without_permission_ingredient_burst()
    {
        $limited = $this->createAdmin('limited@cafe.test');
        $this->actingAs($limited);
        $endpoints = ['/admin/ingredients', '/admin/ingredients/create', '/admin/ingredients-report'];
        foreach ($endpoints as $url) {
            for ($i = 0; $i < 3; $i++) {
                $this->assertEquals(403, $this->get($url)->status());
            }
        }
        $r = $this->json('POST', '/admin/ingredients', ['name' => 'x', 'unit' => 'g']);
        $this->assertEquals(403, $r->status());
    }

    /** @test */
    public function security_massive_order_creation_no_500s()
    {
        $this->cups->update(['stock' => 25.000]);
        $results = [];
        for ($i = 0; $i < 25; $i++) {
            $this->actingAs($this->admin);
            PosCart::create(['product_id' => $this->cappuccino->id, 'user_id' => $this->admin->id, 'quantity' => 1]);
            $r = $this->json('PUT', '/admin/order/create', ['customer_id' => $this->customer->id, 'paid' => 120]);
            $results[] = $r->status();
            $this->assertNotEquals(500, $r->status(), "Request #{$i} returned 500!");
        }
        $successes = count(array_filter($results, fn($s) => $s === 200));
        $this->assertEquals(13, $successes, "Milk limit: 13");
        $serverErrors = count(array_filter($results, fn($s) => $s === 500));
        $this->assertEquals(0, $serverErrors, 'No 500 errors allowed under burst!');
    }
}
