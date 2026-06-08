<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PosCart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $adminB;
    protected Customer $customer;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(["name" => "Admin"]);
        Role::firstOrCreate(["name" => "cashier"]);
        Permission::firstOrCreate(["name" => "ingredient_manage"]);
        Permission::firstOrCreate(["name" => "customer_delete"]);
        Permission::firstOrCreate(["name" => "customer_create"]);
        Permission::firstOrCreate(["name" => "customer_view"]);

        $this->admin = User::factory()->create([
            "name"     => "Admin User",
            "email"    => "admin@test.com",
            "password" => bcrypt("password"),
        ]);
        $this->admin->assignRole("Admin");
        $this->admin->givePermissionTo(["ingredient_manage","customer_delete","customer_create","customer_view"]);

        $this->adminB = User::factory()->create([
            "name"     => "Admin B",
            "email"    => "adminb@test.com",
            "password" => bcrypt("password"),
        ]);
        $this->adminB->assignRole("Admin");

        // Walk-in customer всегда создаётся первым (id=1).
        // CustomerController блокирует удаление id=1 как "Walk-in Guest".
        Customer::create(["name" => "Walk-in Guest", "phone" => "0000000000"]);

        $this->customer = Customer::create([
            "name"    => "Test Customer",
            "phone"   => "1234567890",
            "address" => "Test Address",
        ]);

        $this->product = Product::create([
            "name"           => "Test Coffee",
            "slug"           => "test-coffee",
            "sku"            => "TCF-001",
            "price"          => 100.00,
            "discount"       => 0,
            "discount_type"  => "fixed",
            "purchase_price" => 50.00,
            "quantity"       => 10,
            "status"         => 1,
        ]);
    }

    // ===================== LOGIN TESTS =====================

    /** @test */
    public function login_with_valid_credentials_redirects_to_dashboard()
    {
        $response = $this->post("/login", [
            "email"    => "admin@test.com",
            "password" => "password",
        ]);
        $response->assertStatus(302);
        $this->assertAuthenticatedAs($this->admin);
    }

    /** @test */
    public function login_with_invalid_password_fails()
    {
        $response = $this->post("/login", [
            "email"    => "admin@test.com",
            "password" => "wrong-password",
        ]);
        $response->assertStatus(302);
        $this->assertGuest();
    }

    /** @test */
    public function login_with_nonexistent_email_fails()
    {
        $response = $this->post("/login", [
            "email"    => "nobody@test.com",
            "password" => "password",
        ]);
        $response->assertStatus(302);
        $this->assertGuest();
    }

    /** @test */
    public function suspended_user_cannot_login()
    {
        $this->admin->update(["is_suspended" => true]);
        $response = $this->post("/login", [
            "email"    => "admin@test.com",
            "password" => "password",
        ]);
        $response->assertRedirect();
        $this->assertGuest();
    }

    /** @test */
    public function logout_invalidates_session()
    {
        $this->actingAs($this->admin);
        $response = $this->get("/logout");
        $response->assertRedirect("/");
        $this->assertGuest();
    }

    /** @test */
    public function unauthenticated_cannot_access_admin()
    {
        $response = $this->get("/admin");
        $response->assertRedirect(route("login"));
    }

    // ===================== SUSPENDED ADMIN =====================

    /** @test */
    public function suspended_admin_is_kicked_on_next_request()
    {
        $this->actingAs($this->admin);
        $this->admin->update(["is_suspended" => true]);
        $response = $this->get("/admin");
        // AdminMiddleware должен выкинуть suspended admin
        $response->assertRedirect(route("login"));
        $this->assertGuest();
    }

    // ===================== CART IDOR TESTS =====================

    /** @test */
    public function admin_cannot_increment_another_users_cart_item()
    {
        // Admin B создаёт корзину
        $cartB = PosCart::create([
            "user_id"    => $this->adminB->id,
            "product_id" => $this->product->id,
            "quantity"   => 1,
        ]);

        // Admin A пытается изменить корзину Admin B — должен получить 404
        $this->actingAs($this->admin);
        $response = $this->put("/admin/cart/increment", ["id" => $cartB->id]);
        $response->assertStatus(404);

        // Корзина Admin B не изменилась
        $cartB->refresh();
        $this->assertEquals(1, $cartB->quantity);
    }

    /** @test */
    public function admin_cannot_decrement_another_users_cart_item()
    {
        $cartB = PosCart::create([
            "user_id"    => $this->adminB->id,
            "product_id" => $this->product->id,
            "quantity"   => 3,
        ]);

        $this->actingAs($this->admin);
        $response = $this->put("/admin/cart/decrement", ["id" => $cartB->id]);
        $response->assertStatus(404);

        $cartB->refresh();
        $this->assertEquals(3, $cartB->quantity);
    }

    /** @test */
    public function admin_cannot_delete_another_users_cart_item()
    {
        $cartB = PosCart::create([
            "user_id"    => $this->adminB->id,
            "product_id" => $this->product->id,
            "quantity"   => 2,
        ]);

        $this->actingAs($this->admin);
        $response = $this->put("/admin/cart/delete", ["id" => $cartB->id]);
        $response->assertStatus(404);

        $this->assertDatabaseHas("pos_carts", ["id" => $cartB->id]);
    }

    /** @test */
    public function admin_can_modify_own_cart_item()
    {
        $cartA = PosCart::create([
            "user_id"    => $this->admin->id,
            "product_id" => $this->product->id,
            "quantity"   => 1,
        ]);

        $this->actingAs($this->admin);
        $response = $this->put("/admin/cart/increment", ["id" => $cartA->id]);
        $response->assertStatus(200);

        $cartA->refresh();
        $this->assertEquals(2, $cartA->quantity);
    }

    // ===================== DISCOUNT VALIDATION =====================

    /** @test */
    public function order_discount_cannot_exceed_subtotal()
    {
        PosCart::create([
            "user_id"    => $this->admin->id,
            "product_id" => $this->product->id,
            "quantity"   => 1,  // subtotal = 100
        ]);

        $this->actingAs($this->admin);
        $response = $this->put("/admin/order/create", [
            "customer_id"    => $this->customer->id,
            "order_discount" => 200,  // превышает subtotal 100
            "paid"           => 0,
        ]);

        $response->assertStatus(200);  // заказ создаётся, но discount зафиксирован

        $order = Order::first();
        // discount должен быть зафиксирован на уровне subtotal (100), не 200
        $this->assertLessThanOrEqual($order->sub_total, $order->discount);
        // total никогда не отрицательный
        $this->assertGreaterThanOrEqual(0, $order->total);
        // due никогда не отрицательный
        $this->assertGreaterThanOrEqual(0, $order->due);
    }

    /** @test */
    public function order_total_is_never_negative()
    {
        PosCart::create([
            "user_id"    => $this->admin->id,
            "product_id" => $this->product->id,
            "quantity"   => 1,
        ]);

        $this->actingAs($this->admin);
        $this->put("/admin/order/create", [
            "customer_id"    => $this->customer->id,
            "order_discount" => 99999,
            "paid"           => 0,
        ]);

        $order = Order::first();
        $this->assertGreaterThanOrEqual(0, $order->total);
        $this->assertGreaterThanOrEqual(0, $order->due);
    }

    /** @test */
    public function paid_cannot_exceed_total()
    {
        PosCart::create([
            "user_id"    => $this->admin->id,
            "product_id" => $this->product->id,
            "quantity"   => 1,  // total = 100
        ]);

        $this->actingAs($this->admin);
        $this->put("/admin/order/create", [
            "customer_id" => $this->customer->id,
            "paid"        => 99999,  // превышает total
        ]);

        $order = Order::first();
        // due не может быть отрицательным
        $this->assertGreaterThanOrEqual(0, $order->due);
        // paid не превышает total
        $this->assertLessThanOrEqual($order->total, $order->paid);
    }

    // ===================== CUSTOMER DELETE / SOFT DELETE =====================

    /** @test */
    public function deleting_customer_without_orders_removes_them()
    {
        $this->actingAs($this->admin);
        $newCustomer = Customer::create([
            "name"  => "No Orders",
            "phone" => "9876543210",
        ]);

        $response = $this->delete(route("backend.admin.customers.destroy", $newCustomer->id));
        $response->assertRedirect(route("backend.admin.customers.index"));

        // Soft delete: запись ещё есть в БД с deleted_at
        $this->assertSoftDeleted("customers", ["id" => $newCustomer->id]);
    }

    /** @test */
    public function deleting_customer_with_orders_preserves_financial_history()
    {
        $this->actingAs($this->admin);

        // Создаём заказ для клиента
        $order = Order::create([
            "user_id"     => $this->admin->id,
            "customer_id" => $this->customer->id,
            "sub_total"   => 100,
            "total"       => 100,
            "paid"        => 100,
            "due"         => 0,
            "status"      => 1,
        ]);

        $this->delete(route("backend.admin.customers.destroy", $this->customer->id));

        // Заказ должен остаться в БД
        $this->assertDatabaseHas("orders", ["id" => $order->id]);

        // Клиент архивирован (soft deleted)
        $this->assertSoftDeleted("customers", ["id" => $this->customer->id]);
    }

    // ===================== SECURE ROUTES =====================

    /** @test */
    public function clear_cache_route_requires_authentication()
    {
        $response = $this->get("/admin/clear-cache");
        $response->assertRedirect(route("login"));
    }

    /** @test */
    public function storage_link_route_requires_authentication()
    {
        $response = $this->get("/admin/storage-link");
        $response->assertRedirect(route("login"));
    }

    /** @test */
    public function old_clear_all_route_no_longer_exists()
    {
        $response = $this->get("/clear-all");
        $response->assertStatus(404);
    }

    // ===================== PASSWORD RESET =====================

    /** @test */
    public function forget_password_does_not_reveal_email_existence()
    {
        // Email, которого нет в системе
        $response = $this->post("/forget-password", [
            "email" => "nonexistent@test.com",
        ]);
        // Не должно быть редиректа с "error" — ответ должен быть одинаковым
        $response->assertStatus(302);
        // Редирект на страницу ввода OTP (не раскрывает, что email не найден)
        $response->assertRedirect(route("password.reset"));
    }

    /** @test */
    public function otp_expiry_is_checked_before_password_reset()
    {
        // Создаём просроченный OTP
        $fp = \App\Models\ForgetPassword::create([
            "user_id"          => $this->admin->id,
            "otp"              => "12345",
            "email"            => $this->admin->email,
            "suspend_duration" => now()->subMinutes(10),  // просрочен
        ]);

        session(["reset-email" => $this->admin->email]);

        $response = $this->post("/password-reset", [
            "number_1" => "1",
            "number_2" => "2",
            "number_3" => "3",
            "number_4" => "4",
            "number_5" => "5",
        ]);

        $response->assertRedirect(route("login"));
        $response->assertSessionHas("error");
    }
}
