<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FIX: Добавить soft deletes для customers.
     * Риск: жёсткое удаление клиента с cascadeOnDelete на orders/order_transactions
     * уничтожало всю финансовую историю.
     */
    public function up(): void
    {
        Schema::table("customers", function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table("customers", function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
