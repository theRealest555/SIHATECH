<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('reviews')->whereNotNull('rendezvous_id')->select('rendezvous_id')->groupBy('rendezvous_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate appointment reviews exist. Reconcile them before migration; no records were merged.');
        }
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('moderation_reason', 500)->nullable();
            $table->unique('rendezvous_id');
            $table->index(['status', 'created_at', 'id'], 'reviews_status_time');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique(['rendezvous_id']);
            $table->dropIndex('reviews_status_time');
            $table->dropColumn('moderation_reason');
        });
    }
};
