<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('rendezvous')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->string('mail_status')->default('pending');
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['appointment_id', 'starts_at']);
            $table->index(['mail_status', 'id']);
        });
        Schema::table('rendezvous', fn (Blueprint $table) => $table->index(['statut', 'date_heure']));
    }

    public function down(): void
    {
        Schema::table('rendezvous', fn (Blueprint $table) => $table->dropIndex(['statut', 'date_heure']));
        Schema::dropIfExists('appointment_reminders');
    }
};
