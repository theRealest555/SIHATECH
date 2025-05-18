<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakePatientIdNullableInRendezvous extends Migration
{
    public function up()
    {
        Schema::table('rendezvous', function (Blueprint $table) {
            $table->unsignedBigInteger('patient_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('rendezvous', function (Blueprint $table) {
            $table->unsignedBigInteger('patient_id')->nullable(false)->change();
        });
    }
}