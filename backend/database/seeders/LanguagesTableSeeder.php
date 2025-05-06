<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Language;

class LanguagesTableSeeder extends Seeder
{
    public function run()
    {
        Language::create(['nom' => 'Français']);
        Language::create(['nom' => 'Anglais']);
        Language::create(['nom' => 'Arabe']);
        Language::create(['nom' => 'Espagnol']);
    }
}