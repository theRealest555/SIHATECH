<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('stripe_events', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type');
            $table->unsignedBigInteger('created');
            $table->timestamp('processed_at')->nullable();
        });
        Schema::table('rendezvous', function (Blueprint $table) {
            $table->index(['doctor_id', 'date_heure', 'statut']);
            $table->index(['patient_id', 'date_heure', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::table('rendezvous', function (Blueprint $table) {
            $table->dropIndex(['doctor_id', 'date_heure', 'statut']);
            $table->dropIndex(['patient_id', 'date_heure', 'statut']);
        });
        Schema::dropIfExists('stripe_events');
        Schema::dropIfExists('notifications');
    }
};
