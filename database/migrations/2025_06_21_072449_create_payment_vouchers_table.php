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
        Schema::create('payment_vouchers', function (Blueprint $table) {
            $table->id(); // this will be auto_lager (JV1 code)
            $table->date('date');
            $table->unsignedBigInteger('ac_dr_sid'); // debit account (chart_of_accounts.ac_code)
            $table->unsignedBigInteger('ac_cr_sid'); // credit account (chart_of_accounts.ac_code)
            $table->decimal('amount', 12, 2);
            $table->text('remarks')->nullable();
            $table->json('attachments')->nullable(); // Store multiple files as JSON
            $table->timestamps();

            $table->foreign('ac_dr_sid')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->foreign('ac_cr_sid')->references('id')->on('chart_of_accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_vouchers');
    }
};
