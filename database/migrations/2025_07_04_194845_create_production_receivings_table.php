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
        Schema::create('production_receivings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_id');
            $table->string('challan_no');
            $table->date('receive_date');
            $table->unsignedBigInteger('received_by');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('production_id')->references('id')->on('productions')->onDelete('cascade');
            $table->foreign('received_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_receivings');
    }
};
