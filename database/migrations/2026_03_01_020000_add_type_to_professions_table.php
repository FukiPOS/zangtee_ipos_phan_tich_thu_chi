<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professions', function (Blueprint $table) {
            $table->string('type')->default('OUT'); // 'IN' | 'OUT'
        });
    }

    public function down(): void
    {
        Schema::table('professions', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
