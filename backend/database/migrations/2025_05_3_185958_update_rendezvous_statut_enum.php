<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateRendezvousStatutEnum extends Migration
{
    public function up()
    {
        Schema::table('rendezvous', function (Blueprint $table) {
            $table->enum('statut', ['en_attente', 'confirmé', 'annulé', 'terminé'])
                  ->default('en_attente')
                  ->change();
        });
    }

    public function down()
    {
        Schema::table('rendezvous', function (Blueprint $table) {
            $table->enum('statut', ['en_attente', 'confirmé', 'annulé'])
                  ->default('en_attente')
                  ->change();
        });
    }
}