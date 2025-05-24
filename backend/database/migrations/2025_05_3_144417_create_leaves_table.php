<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason')->nullable(); // Made reason nullable and changed from required
            $table->timestamps();

            // Add indexes for better performance
            $table->index(['doctor_id', 'start_date', 'end_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('leaves');
    }
};
