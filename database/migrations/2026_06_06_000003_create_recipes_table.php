<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 10, 3)->comment('Ingredient quantity per 1 unit of product');
            $table->timestamps();

            $table->unique(['product_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
