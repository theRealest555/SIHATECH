<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Password accounts have both values null. Never infer an identity or
        // merge owners to make historical data fit a new constraint.
        $incomplete = DB::table('users')->where(function ($query) {
            $query->where(function ($query) {
                $query->whereNull('provider')->whereNotNull('provider_id');
            })->orWhere(function ($query) {
                $query->whereNotNull('provider')->whereNull('provider_id');
            })->orWhereRaw("TRIM(provider) = '' OR TRIM(provider_id) = ''");
        })->exists();
        if ($incomplete) {
            throw new RuntimeException('Incomplete or blank social identity mappings exist. Reconcile them with account owners before migration; no records were changed.');
        }
        $duplicates = DB::table('users')->whereNotNull('provider')->whereNotNull('provider_id')
            ->select('provider', 'provider_id')->groupBy('provider', 'provider_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicates) {
            throw new RuntimeException('Duplicate social identity mappings exist. Reconcile account ownership before migration; no records were changed.');
        }
        Schema::table('users', fn (Blueprint $table) => $table->unique(['provider', 'provider_id'], 'users_provider_identity_unique'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique('users_provider_identity_unique'));
    }
};
