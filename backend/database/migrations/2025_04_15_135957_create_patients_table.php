<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('medecin_favori_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->timestamps();
            
            $table->index('medecin_favori_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};