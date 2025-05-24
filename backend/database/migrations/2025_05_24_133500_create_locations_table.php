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
        // Create locations table
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->timestamps();
        });

        // Add location_id to doctors table
        Schema::table('doctors', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('speciality_id')->constrained('locations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove location_id from doctors
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });

        // Drop locations table
        Schema::dropIfExists('locations');
    }
};
