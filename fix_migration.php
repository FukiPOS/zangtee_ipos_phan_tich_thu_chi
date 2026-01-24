<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

if (Schema::hasColumn('transactions', 'profession_id')) {
    Schema::table('transactions', function (Blueprint $table) {
        $table->dropColumn('profession_id');
    });
    echo "Dropped profession_id column.\n";
} else {
    echo "profession_id column does not exist.\n";
}
