<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Language;

class LanguagesTableSeeder extends Seeder
{
    public function run()
    {
        $languages = [
            ['nom' => 'Français'],
            ['nom' => 'Anglais'],
            ['nom' => 'Arabe'],
            ['nom' => 'Espagnol'],
            ['nom' => 'Allemand'],
            ['nom' => 'Italien'],
            ['nom' => 'Portugais'],
            ['nom' => 'Chinois'],
            ['nom' => 'Japonais'],
            ['nom' => 'Russe'],
            ['nom' => 'Hindi'],
            ['nom' => 'Tamazight'],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(
                ['nom' => $language['nom']],
                $language
            );
        }

        // Assign languages to existing doctors
        $doctors = \App\Models\Doctor::all();
        $commonLanguages = Language::whereIn('nom', ['Français', 'Arabe', 'Anglais'])->get();

        foreach ($doctors as $doctor) {
            // Assign 1-3 random languages to each doctor
            $randomLanguages = $commonLanguages->random(rand(1, 3));
            $doctor->languages()->sync($randomLanguages->pluck('id'));
        }
    }
}
