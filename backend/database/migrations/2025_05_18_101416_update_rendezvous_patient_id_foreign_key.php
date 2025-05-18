<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendezvous', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['patient_id']);
            // Add a new foreign key constraint referencing users
            $table->foreign('patient_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('rendezvous', function (Blueprint $table) {
            // Drop the new foreign key
            $table->dropForeign(['patient_id']);
            // Revert to the original foreign key
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
        });
    }
};