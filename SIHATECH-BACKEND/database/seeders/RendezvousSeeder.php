<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RendezvousSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('rendezvous')->insert([
            ['patient_id' => 1, 'doctor_id' => 1, 'date_heure' => now()->addDays(1), 'statut' => 'confirmé'],
            ['patient_id' => 2, 'doctor_id' => 2, 'date_heure' => now()->addDays(2), 'statut' => 'en_attente'],
            ['patient_id' => 3, 'doctor_id' => 3, 'date_heure' => now()->addDays(3), 'statut' => 'annulé'],
            ['patient_id' => 4, 'doctor_id' => 4, 'date_heure' => now()->addDays(4), 'statut' => 'terminé'],
        ]);
        
    }
}

