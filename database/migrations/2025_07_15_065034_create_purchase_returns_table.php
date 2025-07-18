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
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->date('return_date');
            $table->string('reference_no')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('total_amount', 15, 2);
            $table->decimal('net_amount', 15, 2);
            $table->timestamps();
            $table->softdeletes();

            $table->foreign('vendor_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_returns');
    }
};
