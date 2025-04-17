<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DoctorSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('doctors')->insert([
            ['user_id' => 1, 'speciality_id' => 1, 'adresse' => 'Rabat', 'description' => 'Cardiologue', 'horaires' => json_encode([])],
            ['user_id' => 2, 'speciality_id' => 2, 'adresse' => 'Casa', 'description' => 'Dermato', 'horaires' => json_encode([])],
            ['user_id' => 3, 'speciality_id' => 3, 'adresse' => 'Fès', 'description' => 'Pédiatre', 'horaires' => json_encode([])],
            ['user_id' => 4, 'speciality_id' => 4, 'adresse' => 'Tanger', 'description' => 'Gynéco', 'horaires' => json_encode([])],
        ]);
        
    }
}

