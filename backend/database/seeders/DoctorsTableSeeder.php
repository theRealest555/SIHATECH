<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Speciality;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DoctorsTableSeeder extends Seeder
{
    public function run()
    {
        // 1. Vérifier/Créer les spécialités si nécessaire
        if (Speciality::count() === 0) {
            $this->call(SpecialitiesTableSeeder::class);
        }

        // 2. Créer les utilisateurs docteurs si nécessaire
        if (User::where('role', 'medecin')->count() < 4) {
            $doctorsData = [
                [
                    'nom' => 'Dupont',
                    'prenom' => 'Jean',
                    'email' => 'jean.dupont@medicall.com',
                    'password' => Hash::make('Medecin123!'),
                    'role' => 'medecin',
                    'telephone' => '0612345678',
                    'sexe' => 'homme'
                ],
                [
                    'nom' => 'Martin',
                    'prenom' => 'Sophie',
                    'email' => 'sophie.martin@medicall.com',
                    'password' => Hash::make('Medecin123!'),
                    'role' => 'medecin',
                    'telephone' => '0623456789',
                    'sexe' => 'femme'
                ],
                [
                    'nom' => 'Bernard',
                    'prenom' => 'Pierre',
                    'email' => 'pierre.bernard@medicall.com',
                    'password' => Hash::make('Medecin123!'),
                    'role' => 'medecin',
                    'telephone' => '0634567890',
                    'sexe' => 'homme'
                ],
                [
                    'nom' => 'Petit',
                    'prenom' => 'Marie',
                    'email' => 'marie.petit@medicall.com',
                    'password' => Hash::make('Medecin123!'),
                    'role' => 'medecin',
                    'telephone' => '0645678901',
                    'sexe' => 'femme'
                ]
            ];

            foreach ($doctorsData as $doctorUser) {
                User::create($doctorUser);
            }
        }

        // 3. Associer les utilisateurs aux docteurs
        $users = User::where('role', 'medecin')->limit(4)->get();
        $specialities = Speciality::all();

        foreach ($users as $index => $user) {
            Doctor::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'speciality_id' => $specialities[$index % $specialities->count()]->id,
                    'description' => 'Docteur en ' . $specialities[$index % $specialities->count()]->nom . ' avec ' . rand(5, 20) . ' ans d\'expérience',
                    'horaires' => json_encode([
                        'lundi' => '09:00-12:00, 14:00-18:00',
                        'mercredi' => '09:00-12:00',
                        'vendredi' => '14:00-18:00'
                    ]),
                    'is_verified' => true,
                    'is_active' => true
                ]
            );
        }

    }
}