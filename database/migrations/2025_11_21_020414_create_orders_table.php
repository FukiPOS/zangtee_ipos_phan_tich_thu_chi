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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('foodbook_order_id')->nullable();
            $table->string('source_fb_id')->nullable();
            $table->string('store_uid')->nullable();
            $table->string('tran_date')->nullable();
            $table->string('tran_id')->unique();
            $table->string('tran_no')->nullable();
            $table->string('start_date')->nullable();
            $table->integer('amount_origin')->nullable();
            $table->string('payment_method_id')->nullable();
            $table->integer('payment_amout')->nullable();
            $table->text('raw_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
