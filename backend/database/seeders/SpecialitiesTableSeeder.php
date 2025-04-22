<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Speciality;

class SpecialitiesTableSeeder extends Seeder
{
    public function run()
    {
        Speciality::create([
            'nom' => 'Cardiologie',
            'description' => 'Spécialité médicale concernant le cœur et les vaisseaux sanguins'
        ]);

        Speciality::create([
            'nom' => 'Dermatologie',
            'description' => 'Spécialité médicale concernant la peau, les cheveux et les ongles'
        ]);

        Speciality::create([
            'nom' => 'Pédiatrie',
            'description' => 'Spécialité médicale concernant les enfants'
        ]);

        Speciality::create([
            'nom' => 'Neurologie',
            'description' => 'Spécialité médicale concernant le système nerveux'
        ]);
    }
}