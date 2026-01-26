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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('order_payment_method_id')->nullable();
            $table->decimal('order_payment_amount', 15, 2)->nullable();
            $table->string('order_payment_method_name')->nullable();
            $table->float('order_distance')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'order_payment_method_id',
                'order_payment_amount',
                'order_payment_method_name',
                'order_distance'
            ]);
        });
    }
};
