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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('ipos_id')->unique(); // The 'id' from IPOS uuid
            $table->string('name');
            $table->string('brand_uid')->nullable();
            $table->string('company_uid')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->text('sale_note')->nullable();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('flag', ['valid', 'review', 'invalid'])->default('review');
            $table->string('review_status')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['flag', 'review_status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('sale_note');
        });

        Schema::dropIfExists('stores');
    }
};
