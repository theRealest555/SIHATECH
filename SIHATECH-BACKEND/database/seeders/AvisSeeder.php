<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AvisSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('avis')->insert([
            ['patient_id' => 1, 'doctor_id' => 1, 'note' => 5, 'commentaire' => 'Excellent médecin.', 'date' => now()],
            ['patient_id' => 2, 'doctor_id' => 2, 'note' => 4, 'commentaire' => 'Très bien.', 'date' => now()],
            ['patient_id' => 3, 'doctor_id' => 3, 'note' => 3, 'commentaire' => 'Correct.', 'date' => now()],
            ['patient_id' => 4, 'doctor_id' => 4, 'note' => 2, 'commentaire' => 'Pas satisfait.', 'date' => now()],
        ]);
        
    }
}

