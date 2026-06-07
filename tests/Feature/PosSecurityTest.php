<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\PosCart;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Admin']);
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'ingredient_manage']);
        Permission::firstOrCreate(['name' => 'sale_create']);
        Permission::firstOrCreate(['name' => 'sale_view']);

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->assignRole('Admin');
        $this->admin->givePermissionTo('ingredient_manage');

        $this->cashier = User::factory()->create([
            'name' => 'Cashier User',
            'email' => 'cashier@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->cashier->assignRole('cashier');
        $this->cashier->givePermissionTo(['sale_create', 'sale_view']);

        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '1234567890',
            'address' => 'Test Address',
        ]);
    }

    /** @test */
    public function cashier_cannot_access_admin_dashboard()
    {
        $this->actingAs($this->cashier);
        $response = $this->get('/admin');
        $response->assertStatus(403);
    }

    /** @test */
    public function cashier_cannot_access_admin_products()
    {
        $this->actingAs($this->cashier);
        $response = $this->get('/admin/products');
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_access_admin_dashboard()
    {
        $this->actingAs($this->admin);
        $response = $this->get('/admin');
        $response->assertStatus(200);
    }

    /** @test */
    public function cashier_cannot_access_ingredients_index()
    {
        $this->actingAs($this->cashier);
        $response = $this->get('/admin/ingredients');
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_without_ingredient_permission_cannot_access_ingredients()
    {
        $adminNoPerm = User::factory()->create([
            'name' => 'Limited Admin',
            'email' => 'limited@test.com',
            'password' => bcrypt('password'),
        ]);
        $adminNoPerm->assignRole('Admin');
        $this->actingAs($adminNoPerm);
        $response = $this->get('/admin/ingredients');
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_with_ingredient_permission_can_access_ingredients()
    {
        $this->actingAs($this->admin);
        $response = $this->get('/admin/ingredients');
        $response->assertStatus(200);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_order()
    {
        $response = $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 100,
        ]);
        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function cashier_cannot_create_order()
    {
        $this->actingAs($this->cashier);
        $response = $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 100,
        ]);
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_order()
    {
        $this->actingAs($this->admin);
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TST-001',
            'price' => 50.00,
            'discount' => 0,
            'discount_type' => 'fixed',
            'purchase_price' => 25.00,
            'quantity' => 10,
            'status' => 1,
        ]);
        PosCart::create([
            'product_id' => $product->id,
            'user_id' => $this->admin->id,
            'quantity' => 1,
        ]);
        $response = $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid' => 50,
        ]);
        $response->assertStatus(200);
    }

    /** @test */
    public function user_without_any_role_cannot_access_admin()
    {
        $noRoleUser = User::factory()->create([
            'name' => 'No Role User',
            'email' => 'norole@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($noRoleUser);
        $response = $this->get('/admin');
        $response->assertStatus(403);
    }

    /** @test */
    public function suspended_admin_can_still_access_admin()
    {
        $suspendedAdmin = User::factory()->create([
            'name' => 'Suspended Admin',
            'email' => 'suspended@test.com',
            'password' => bcrypt('password'),
            'is_suspended' => 1,
        ]);
        $suspendedAdmin->assignRole('Admin');
        $this->actingAs($suspendedAdmin);
        $response = $this->get('/admin');
        $response->assertStatus(200);
    }

    /** @test */
    public function guest_is_redirected_to_login()
    {
        $response = $this->get('/admin');
        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function ingredient_create_requires_permission()
    {
        $adminNoPerm = User::factory()->create([
            'name' => 'Admin No Perm',
            'email' => 'noperm@test.com',
            'password' => bcrypt('password'),
        ]);
        $adminNoPerm->assignRole('Admin');
        $this->actingAs($adminNoPerm);
        $response = $this->get('/admin/ingredients/create');
        $response->assertStatus(403);
    }

    /** @test */
    public function ingredient_store_requires_permission()
    {
        $adminNoPerm = User::factory()->create([
            'name' => 'Admin No Perm',
            'email' => 'noperm2@test.com',
            'password' => bcrypt('password'),
        ]);
        $adminNoPerm->assignRole('Admin');
        $this->actingAs($adminNoPerm);
        $response = $this->post('/admin/ingredients', [
            'name' => 'Test Ingredient',
            'unit' => 'g',
            'stock' => 100,
            'cost' => 0.10,
        ]);
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_with_permission_can_create_ingredient()
    {
        $this->actingAs($this->admin);
        $response = $this->post('/admin/ingredients', [
            'name' => 'New Ingredient',
            'unit' => 'ml',
            'stock' => 500,
            'cost' => 0.20,
        ]);
        $response->assertStatus(302);
        $this->assertDatabaseHas('ingredients', ['name' => 'New Ingredient']);
    }

    /** @test */
    public function recipe_routes_require_ingredient_permission()
    {
        $adminNoPerm = User::factory()->create([
            'name' => 'Admin No Perm',
            'email' => 'noperm3@test.com',
            'password' => bcrypt('password'),
        ]);
        $adminNoPerm->assignRole('Admin');
        $this->actingAs($adminNoPerm);

        $product = Product::create([
            'name' => 'Test',
            'slug' => 'test',
            'sku' => 'TST-001',
            'price' => 10,
            'discount' => 0,
            'discount_type' => 'fixed',
            'purchase_price' => 5,
            'quantity' => 10,
            'status' => 1,
        ]);
        $response = $this->get("/admin/products/{$product->id}/recipes");
        $response->assertStatus(403);
    }
}
