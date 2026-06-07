<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('unit', ['g', 'ml', 'pcs'])->default('g');
            $table->decimal('stock', 10, 3)->default(0)
                ->comment('Current stock in given unit — must be >= 0');
            $table->decimal('cost', 10, 2)->default(0)
                ->comment('Cost per unit — must be >= 0');
            $table->timestamps();
        });

        // FIX BUG-4: DB-level guard against negative stock/cost
        // Enforced on MySQL 8.0.16+ and PostgreSQL; silently ignored on older MySQL.
        try {
            DB::statement(
                'ALTER TABLE `ingredients`
                 ADD CONSTRAINT `chk_ingredients_stock_non_negative` CHECK (`stock` >= 0),
                 ADD CONSTRAINT `chk_ingredients_cost_non_negative`  CHECK (`cost`  >= 0)'
            );
        } catch (\Throwable $e) {
            // Older MySQL / SQLite: constraint unsupported — application layer still guards.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
