<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('barcode')->nullable(); // optional
            $table->text('description')->nullable();

            $table->decimal('manufacturing_cost', 10, 2)->default(0);
            $table->unsignedBigInteger('measurement_unit');
            $table->string('item_type', 10)->nullable();
            $table->integer('opening_stock')->default(0); // or decimal if partial stocks allowed

            $table->softDeletes();
            $table->timestamps();

            $table->foreign('measurement_unit')->references('id')->on('measurement_units')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('product_categories')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
