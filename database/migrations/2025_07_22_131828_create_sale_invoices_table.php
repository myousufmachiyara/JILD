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
            $table->string('invoice_no')->unique();
            $table->date('date');
            $table->unsignedBigInteger('account_id');
            $table->string('type'); // distinguishes Cash and Credit
            $table->decimal('convance_charges', 10, 2)->nullable();
            $table->decimal('other_expenses', 10, 2)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softdeletes();

            $table->foreign('account_id')->references('id')->on('chart_of_accounts');
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
