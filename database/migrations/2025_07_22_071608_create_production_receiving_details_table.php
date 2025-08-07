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
        Schema::create('production_receiving_details', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->unsignedBigInteger('production_receiving_id');
            $table->unsignedBigInteger('production_id'); // Add this to link directly to production
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variation_id');

            // Costing
            $table->decimal('raw_material_cost', 10, 4)->default(0); // avg. cost of raw material used per unit
            $table->decimal('manufacturing_cost', 10, 4)->default(0); // manual or fixed cost per unit
            $table->decimal('total_unit_cost', 10, 4); // total cost per unit
            $table->decimal('received_qty', 10, 4); // total received quantity

            // Barcode / traceability
            $table->string('barcode_prefix')->nullable(); // e.g. PRD-00001-V1
            $table->string('barcode_label')->nullable();  // full code if generated here
            $table->string('serial_format')->nullable(); // for traceable tags (optional)

            // Utility
            $table->text('remarks')->nullable();
            $table->decimal('total', 10, 2); // total cost for this row: (unit cost * qty)
            
            $table->timestamps();

            // Foreign Keys
            $table->foreign('production_receiving_id')->references('id')->on('production_receivings')->onDelete('cascade'); 
            $table->foreign('production_id')->references('id')->on('productions')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('variation_id')->references('id')->on('product_variations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_receiving_details');
    }
};
