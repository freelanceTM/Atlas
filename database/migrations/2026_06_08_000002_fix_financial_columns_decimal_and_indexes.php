<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ШАГ 4: DOUBLE → DECIMAL(12,2) для финансовых полей.
     * Риск DOUBLE: IEEE 754 floating point — накапливает ошибки округления.
     * Например: 0.1 + 0.2 = 0.30000000000000004 — в финансовой системе недопустимо.
     * DECIMAL(12,2) хранит точные значения с 2 знаками после запятой.
     *
     * ШАГ 3: Добавить недостающие индексы для устранения N+1 и медленных запросов.
     */
    public function up(): void
    {
        // ── orders ────────────────────────────────────────────────────────
        Schema::table("orders", function (Blueprint $table) {
            // DOUBLE → DECIMAL
            $table->decimal("sub_total", 12, 2)->default(0)->change();
            $table->decimal("discount",  12, 2)->default(0)->change();
            $table->decimal("total",     12, 2)->default(0)->change();
            $table->decimal("paid",      12, 2)->default(0)->change();
            $table->decimal("due",       12, 2)->default(0)->change();

            // Индексы для фильтров дашборда и DataTables
            $table->index("created_at",  "idx_orders_created_at");
            $table->index("customer_id", "idx_orders_customer_id");
            $table->index("status",      "idx_orders_status");
            $table->index("order_type",  "idx_orders_order_type");
        });

        // ── order_products ────────────────────────────────────────────────
        Schema::table("order_products", function (Blueprint $table) {
            $table->decimal("price",          12, 2)->default(0)->change();
            $table->decimal("purchase_price", 12, 2)->default(0)->change();
            $table->decimal("discount",       12, 2)->default(0)->change();
            $table->decimal("sub_total",      12, 2)->default(0)->change();
            $table->decimal("total",          12, 2)->default(0)->change();

            // N+1 fix: быстрый поиск товаров в заказе
            $table->index("order_id",   "idx_order_products_order_id");
            $table->index("product_id", "idx_order_products_product_id");
        });

        // ── order_transactions ────────────────────────────────────────────
        Schema::table("order_transactions", function (Blueprint $table) {
            $table->decimal("amount", 12, 2)->unsigned()->change();

            $table->index("order_id",    "idx_order_txn_order_id");
            $table->index("customer_id", "idx_order_txn_customer_id");
            $table->index("created_at",  "idx_order_txn_created_at");
        });

        // ── ingredients ───────────────────────────────────────────────────
        Schema::table("ingredients", function (Blueprint $table) {
            $table->index("name", "idx_ingredients_name");
        });

        // ── recipes ───────────────────────────────────────────────────────
        Schema::table("recipes", function (Blueprint $table) {
            // Быстрый JOIN при списании ингредиентов при оформлении заказа
            $table->index("ingredient_id", "idx_recipes_ingredient_id");
        });

        // ── products ─────────────────────────────────────────────────────
        Schema::table("products", function (Blueprint $table) {
            $table->index("status",      "idx_products_status");
            $table->index("quantity",    "idx_products_quantity");
            $table->index("category_id", "idx_products_category_id");
        });

        // ── pos_carts ────────────────────────────────────────────────────
        Schema::table("pos_carts", function (Blueprint $table) {
            // Критичный индекс: каждый запрос к корзине фильтрует по user_id
            $table->index("user_id", "idx_pos_carts_user_id");
        });

        // ── purchases ────────────────────────────────────────────────────
        Schema::table("purchases", function (Blueprint $table) {
            $table->index("created_at", "idx_purchases_created_at");
        });
    }

    public function down(): void
    {
        Schema::table("orders", function (Blueprint $table) {
            $table->dropIndex("idx_orders_created_at");
            $table->dropIndex("idx_orders_customer_id");
            $table->dropIndex("idx_orders_status");
            $table->dropIndex("idx_orders_order_type");
            $table->double("sub_total")->default(0)->change();
            $table->double("discount")->default(0)->change();
            $table->double("total")->default(0)->change();
            $table->double("paid")->default(0)->change();
            $table->double("due")->default(0)->change();
        });
        Schema::table("order_products", function (Blueprint $table) {
            $table->dropIndex("idx_order_products_order_id");
            $table->dropIndex("idx_order_products_product_id");
        });
        Schema::table("order_transactions", function (Blueprint $table) {
            $table->dropIndex("idx_order_txn_order_id");
            $table->dropIndex("idx_order_txn_customer_id");
            $table->dropIndex("idx_order_txn_created_at");
        });
        Schema::table("pos_carts", function (Blueprint $table) {
            $table->dropIndex("idx_pos_carts_user_id");
        });
    }
};
