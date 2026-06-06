<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('unit', ['g', 'ml', 'pcs'])->default('g');
            $table->decimal('stock', 10, 3)->default(0)->comment('Current stock in given unit');
            $table->decimal('cost', 10, 2)->default(0)->comment('Cost per unit');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
