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
        Schema::create('sale_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_invoice_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variation_id'); // size/color variation
            $table->unsignedBigInteger('production_id'); // for lot/production cost
            $table->decimal('cost_price', 10, 2); // from production
            $table->decimal('sale_price', 10, 2); // actual selling price
            $table->integer('quantity');
            $table->timestamps();

            $table->foreign('sale_invoice_id')->references('id')->on('sale_invoices');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('variation_id')->references('id')->on('product_variations');
            $table->foreign('production_id')->references('id')->on('productions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_invoice_items');
    }
};
