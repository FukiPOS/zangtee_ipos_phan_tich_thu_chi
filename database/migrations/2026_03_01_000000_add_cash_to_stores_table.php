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
        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('cash', 15, 0)->default(0);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('cash_processed')->default(false);
            $table->decimal('cash_amount', 15, 0)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('cash');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['cash_processed', 'cash_amount']);
        });
    }
};
