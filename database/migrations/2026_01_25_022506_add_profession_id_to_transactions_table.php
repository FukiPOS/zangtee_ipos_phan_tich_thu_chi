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
            $table->foreignId('profession_id')->nullable()->after('store_uid')->index();
        });

        // Migrate data
        $rows = DB::table('transactions')
            ->select('profession_name', 'profession_uid')
            ->whereNotNull('profession_name')
            ->where('profession_name', '!=', '')
            ->distinct()
            ->get();

        foreach ($rows as $row) {
            $profession = null;

            // Try to find by UID if it exists
            if (!empty($row->profession_uid)) {
                $profession = DB::table('professions')->where('ipos_profession_uid', $row->profession_uid)->first();
            }

            // Fallback to name if not found by UID (or UID is empty - local profession)
            if (!$profession) {
                $profession = DB::table('professions')->where('name', $row->profession_name)->first();
            }

            // Create if not exists
            if (!$profession) {
                $professionId = DB::table('professions')->insertGetId([
                    'name' => $row->profession_name,
                    'ipos_profession_uid' => !empty($row->profession_uid) ? $row->profession_uid : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $professionId = $profession->id;
                // Update UID if it was missing and we found it now? (optional, possibly unsafe)
            }

            // Update transactions
            $query = DB::table('transactions')
                ->where('profession_name', $row->profession_name);
            
            if (!empty($row->profession_uid)) {
                $query->where('profession_uid', $row->profession_uid);
            } else {
                $query->whereNull('profession_uid')->orWhere('profession_uid', '');
            }
            
            $query->update(['profession_id' => $professionId]);
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['profession_name', 'profession_uid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('profession_name')->nullable();
            $table->string('profession_uid')->nullable();
        });

        // Restore data (best effort)
        $transactions = DB::table('transactions')->groupBy('profession_id')->get(['profession_id']); // get unique IDs needed
        // Actually better to join
        DB::statement("
            UPDATE transactions t
            JOIN professions p ON t.profession_id = p.id
            SET t.profession_name = p.name, t.profession_uid = p.ipos_profession_uid
        ");

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('profession_id');
        });
    }
};
