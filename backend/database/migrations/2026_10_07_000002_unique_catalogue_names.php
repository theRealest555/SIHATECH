<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Inspect both tables before any DDL. Historical duplicates require deliberate reconciliation.
        foreach (['specialities', 'languages'] as $table) {
            if (DB::table($table)->select('nom')->groupBy('nom')->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Duplicate names in '.$table.'. Reconcile existing records before adding the unique index; no rows have been merged or removed.');
            }
        }
        foreach (['specialities', 'languages'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique('nom'));
        }
    }

    public function down(): void
    {
        foreach (['specialities', 'languages'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique(['nom']));
        }
    }
};
