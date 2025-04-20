<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SpecialitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('specialities')->insert([
            ['nom' => 'Cardiologie', 'description' => 'Spécialiste du cœur'],
            ['nom' => 'Dermatologie', 'description' => 'Peau et allergies'],
            ['nom' => 'Gynécologie', 'description' => 'Médecin des femmes'],
            ['nom' => 'Pédiatrie', 'description' => 'Médecin pour enfants'],
        ]);
    }
}
