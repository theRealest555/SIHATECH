<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['created_at', 'id'], 'audit_logs_time_order');
            $table->index(['user_id', 'created_at', 'id'], 'audit_logs_actor_time');
            $table->index(['action', 'created_at', 'id'], 'audit_logs_action_time');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex('audit_logs_time_order');
            $table->dropIndex('audit_logs_actor_time');
            $table->dropIndex('audit_logs_action_time');
        });
        // Preserve NULL actors from deleted accounts; rollback must not discard retained history.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
