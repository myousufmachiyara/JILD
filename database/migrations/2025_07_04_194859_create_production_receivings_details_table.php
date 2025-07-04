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
        Schema::create('production_receivings_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('used_qty', 15, 2)->default(0);
            $table->decimal('waste_qty', 15, 2)->default(0);
            $table->decimal('missed_qty', 15, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('receiving_id')->references('id')->on('production_receivings')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_receivings_details');
    }
};
