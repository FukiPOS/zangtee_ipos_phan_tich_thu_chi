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
        /*amount
:
40000
brand_uid
:
"7b7bb511-84dd-45d0-81ad-edde5539c22f"
cash_id
:
"CAIO_2EE6ZG6EZKWWESY2K400S9"
company_uid
:
"c4e4c3c9-e177-4c62-844a-401d37ca1435"
created_at
:
"2025-11-18T16:20:08.765Z"
created_by
:
"ltt.order@zangtee.vn"
deleted
:
false
deleted_at
:
null
deleted_by
:
null
employee_email
:
"ltt.order@zangtee.vn"
employee_name
:
null
id
:
"c0248070-d1e3-4877-95a6-ffa3a647ffdb"
note
:
"nhận đồ từ bếp lần 3"
payment_method_id
:
"COD"
payment_method_name
:
"CASH"
profession_name
:
"Chi phí vận chuyển"
profession_uid
:
"e38c5bf7-b860-4539-b03e-751998f171b3"
shift_id
:
"212243673375BUZ0L7D90A"
shift_name
:
null
store_uid
:
"96da0392-e0de-4575-a83c-82412d554812"
time
:
1763482808086
type
:
"OUT"
updated_at
:
"2025-11-18T16:22:38.570Z"
updated_by
:
"ltt.order@zangtee.vn"
*/
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('amount')->nullable();
            $table->string('brand_uid')->nullable();
            $table->string('cash_id')->nullable();
            $table->string('company_uid')->nullable();
            $table->string('created_at')->nullable();
            $table->string('created_by')->nullable();
            $table->boolean('deleted')->default(false);
            $table->string('deleted_at')->nullable();
            $table->string('deleted_by')->nullable();
            $table->string('employee_email')->nullable();
            $table->string('employee_name')->nullable();
            $table->string('note')->nullable();
            $table->string('payment_method_id')->nullable();
            $table->string('payment_method_name')->nullable();
            $table->string('profession_name')->nullable();
            $table->string('profession_uid')->nullable();
            $table->integer('category_id')->nullable();
            $table->string('shift_id')->nullable();
            $table->string('shift_name')->nullable();
            $table->string('store_uid')->nullable();
            $table->string('time')->nullable();
            $table->string('type')->nullable();
            $table->boolean('mark_as_done')->default(false);
            $table->string('updated_at')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
