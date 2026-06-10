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

/**
 * Тесты автоматического списания/восстановления ингредиентов.
 *
 * Требуемые тест-кейсы:
 *   1. ingredient_is_deducted_after_sale
 *   2. multiple_product_quantity_deducts_correct_stock
 *   3. sale_fails_when_ingredient_stock_is_insufficient
 *   4. cancelling_order_restores_ingredient_stock
 */
class IngredientDeductionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Admin']);
        Permission::firstOrCreate(['name' => 'ingredient_manage']);

        $this->admin = User::factory()->create([
            'email'    => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->assignRole('Admin');
        $this->admin->givePermissionTo('ingredient_manage');

        $this->customer = Customer::create([
            'name'    => 'Test Customer',
            'phone'   => '9999999999',
            'address' => 'Test Address',
        ]);

        $this->actingAs($this->admin);
    }

    /**
     * Вспомогательный метод: создать товар с рецептом.
     *
     * @param  Ingredient  $ingredient
     * @param  float        $recipeQty  Количество ингредиента на 1 единицу товара
     * @return Product
     */
    private function makeProductWithRecipe(Ingredient $ingredient, float $recipeQty): Product
    {
        $product = Product::create([
            'name'           => 'Капучино',
            'slug'           => 'cappuccino-' . uniqid(),
            'sku'            => 'CAP-' . uniqid(),
            'price'          => 150.00,
            'discount'       => 0,
            'discount_type'  => 'fixed',
            'purchase_price' => 50.00,
            'quantity'       => 100,
            'status'         => 1,
        ]);

        Recipe::create([
            'product_id'    => $product->id,
            'ingredient_id' => $ingredient->id,
            'quantity'      => $recipeQty,
        ]);

        return $product;
    }

    /**
     * Вспомогательный метод: добавить товар в корзину и оформить заказ.
     *
     * @return \Illuminate\Testing\TestResponse
     */
    private function placeOrder(Product $product, int $cartQty, float $paid = 9999): \Illuminate\Testing\TestResponse
    {
        PosCart::create([
            'user_id'    => $this->admin->id,
            'product_id' => $product->id,
            'quantity'   => $cartQty,
        ]);

        return $this->put('/admin/order/create', [
            'customer_id' => $this->customer->id,
            'paid'        => $paid,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Ингредиент списывается после продажи
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function ingredient_is_deducted_after_sale(): void
    {
        // Молоко: 1000 мл на складе
        $milk = Ingredient::create([
            'name'  => 'Молоко',
            'unit'  => 'ml',
            'stock' => 1000.000,
            'cost'  => 0.01,
        ]);

        // Капучино: рецепт — 200 мл молока на 1 порцию
        $cappuccino = $this->makeProductWithRecipe($milk, 200.000);

        // Продаём 1 капучино
        $response = $this->placeOrder($cappuccino, 1);

        $response->assertStatus(200);

        $milk->refresh();

        // 1000 - 200 = 800
        $this->assertEquals(800.000, (float) $milk->stock,
            'После продажи 1 порции должно остаться 800 мл молока.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Несколько единиц товара списывают правильное количество
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function multiple_product_quantity_deducts_correct_stock(): void
    {
        // Кофе: 300 г на складе
        $coffee = Ingredient::create([
            'name'  => 'Кофе',
            'unit'  => 'g',
            'stock' => 300.000,
            'cost'  => 0.05,
        ]);

        // Капучино: рецепт — 30 г кофе на 1 порцию
        $cappuccino = $this->makeProductWithRecipe($coffee, 30.000);

        // Продаём 3 капучино → нужно 3 × 30 = 90 г
        $response = $this->placeOrder($cappuccino, 3);

        $response->assertStatus(200);

        $coffee->refresh();

        // 300 - 90 = 210
        $this->assertEquals(210.000, (float) $coffee->stock,
            'При продаже 3 порций должно списаться 90 г кофе, остаток — 210 г.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Продажа отклоняется при нехватке ингредиента
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function sale_fails_when_ingredient_stock_is_insufficient(): void
    {
        // Молоко: только 150 мл на складе
        $milk = Ingredient::create([
            'name'  => 'Молоко',
            'unit'  => 'ml',
            'stock' => 150.000,
            'cost'  => 0.01,
        ]);

        // Капучино: рецепт — 200 мл молока на 1 порцию
        $cappuccino = $this->makeProductWithRecipe($milk, 200.000);

        // Пробуем продать 1 капучино (нужно 200 мл, есть 150 мл)
        $response = $this->placeOrder($cappuccino, 1);

        // Ожидаем ошибку 422
        $response->assertStatus(422);

        // Заказ не должен быть создан
        $this->assertDatabaseCount('orders', 0);

        // Остаток не должен измениться
        $milk->refresh();
        $this->assertEquals(150.000, (float) $milk->stock,
            'При нехватке ингредиента остаток не должен измениться.');

        // Сообщение об ошибке должно содержать название ингредиента
        $message = $response->json('message');
        $this->assertStringContainsString('Молоко', $message,
            'Сообщение об ошибке должно содержать название ингредиента.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Отмена заказа восстанавливает ингредиенты на складе
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function cancelling_order_restores_ingredient_stock(): void
    {
        // Молоко: 1000 мл на складе
        $milk = Ingredient::create([
            'name'  => 'Молоко',
            'unit'  => 'ml',
            'stock' => 1000.000,
            'cost'  => 0.01,
        ]);

        // Капучино: рецепт — 200 мл молока на 1 порцию
        $cappuccino = $this->makeProductWithRecipe($milk, 200.000);

        // Продаём 2 капучино → списывается 400 мл
        $response = $this->placeOrder($cappuccino, 2);
        $response->assertStatus(200);

        $milk->refresh();
        $this->assertEquals(600.000, (float) $milk->stock,
            'После продажи должно остаться 600 мл молока.');

        // Получаем ID созданного заказа
        $orderId = $response->json('order.id');
        $this->assertNotNull($orderId, 'Ответ должен содержать ID заказа.');

        // Отменяем заказ
        $cancelResponse = $this->delete("/admin/orders/{$orderId}/cancel");
        $cancelResponse->assertStatus(200);

        // Молоко должно вернуться на склад: 600 + 400 = 1000 мл
        $milk->refresh();
        $this->assertEquals(1000.000, (float) $milk->stock,
            'После отмены заказа ингредиенты должны быть полностью восстановлены (1000 мл).');

        // Заказ должен быть помечен как отменённый
        $order = Order::find($orderId);
        $this->assertTrue((bool) $order->is_returned,
            'Заказ должен быть помечен как отменённый (is_returned = true).');
    }
}
