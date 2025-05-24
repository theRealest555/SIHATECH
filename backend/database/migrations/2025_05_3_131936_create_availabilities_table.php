<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->onDelete('cascade');
            $table->string('day_of_week');
            $table->string('time_range'); // e.g., "09:00-12:00"
            $table->timestamps();

            // Add indexes for better performance
            $table->index(['doctor_id', 'day_of_week']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('availabilities');
    }
};
