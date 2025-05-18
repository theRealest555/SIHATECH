<?php

// database/migrations/YYYY_MM_DD_create_availabilities_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAvailabilitiesTable extends Migration
{
    public function up()
    {
        Schema::create('availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->onDelete('cascade');
            $table->string('day_of_week');
            $table->string('time_range'); // e.g., "09:00-12:00"
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('availabilities');
    }
}
