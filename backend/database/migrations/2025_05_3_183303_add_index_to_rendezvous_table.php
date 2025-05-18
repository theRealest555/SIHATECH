<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexToRendezvousTable extends Migration
{
    public function up()
    {
        Schema::table('rendezvous', function (Blueprint $table) {
            $table->index(['doctor_id', 'date_heure', 'statut']);
        });
    }

    public function down()
    {
        Schema::table('rendezvous', function (Blueprint $table) {
            $table->dropIndex(['doctor_id', 'date_heure', 'statut']);
        });
    }
}