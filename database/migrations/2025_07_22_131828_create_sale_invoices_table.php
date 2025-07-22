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
        Schema::create('sale_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coa_id'); // customer
            $table->date('date');
            $table->string('bill_no')->nullable();
            $table->string('ref_no')->nullable();
            $table->string('payment_terms')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('charges', 12, 2)->nullable();
            $table->decimal('discount', 12, 2)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('coa_id')->references('id')->on('chart_of_accounts');
            $table->foreign('created_by')->references('id')->on('users');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_invoices');
    }
};
