<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 10, 3)
                ->comment('Ingredient quantity per 1 unit of product — must be > 0');
            $table->timestamps();

            $table->unique(['product_id', 'ingredient_id']);
        });

        // FIX BUG-4: DB-level guard — recipe quantity must always be positive
        try {
            DB::statement(
                'ALTER TABLE `recipes`
                 ADD CONSTRAINT `chk_recipes_quantity_positive` CHECK (`quantity` > 0)'
            );
        } catch (\Throwable $e) {
            // Older MySQL / SQLite: constraint unsupported — validation layer guards.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
