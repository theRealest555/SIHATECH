<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\User;
use App\Models\Doctor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PatientsTableSeeder extends Seeder
{
    public function run()
    {
        // 1. Vérifier/Créer les docteurs si nécessaire
        if (Doctor::count() === 0) {
            $this->call(DoctorsTableSeeder::class);
        }

        // 2. Créer les utilisateurs patients si nécessaire
        if (User::where('role', 'patient')->count() < 4) {
            $patientsData = [
                [
                    'nom' => 'Durand',
                    'prenom' => 'Alice',
                    'email' => 'alice.durand@patient.com',
                    'password' => Hash::make('Patient123!'),
                    'role' => 'patient',
                    'telephone' => '0656789012',
                    'sexe' => 'femme',
                    'date_de_naissance' => '1990-05-15'
                ],
                [
                    'nom' => 'Leroy',
                    'prenom' => 'Thomas',
                    'email' => 'thomas.leroy@patient.com',
                    'password' => Hash::make('Patient123!'),
                    'role' => 'patient',
                    'telephone' => '0667890123',
                    'sexe' => 'homme',
                    'date_de_naissance' => '1985-08-22'
                ],
                [
                    'nom' => 'Moreau',
                    'prenom' => 'Sophie',
                    'email' => 'sophie.moreau@patient.com',
                    'password' => Hash::make('Patient123!'),
                    'role' => 'patient',
                    'telephone' => '0678901234',
                    'sexe' => 'femme',
                    'date_de_naissance' => '1992-11-30'
                ],
                [
                    'nom' => 'Simon',
                    'prenom' => 'Pierre',
                    'email' => 'pierre.simon@patient.com',
                    'password' => Hash::make('Patient123!'),
                    'role' => 'patient',
                    'telephone' => '0689012345',
                    'sexe' => 'homme',
                    'date_de_naissance' => '1988-04-12'
                ]
            ];

            foreach ($patientsData as $patientUser) {
                User::create($patientUser);
            }
        }

        // 3. Créer les profils patients
        $users = User::where('role', 'patient')->limit(4)->get();
        $doctors = Doctor::all();

        foreach ($users as $user) {
            Patient::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'medecin_favori_id' => $doctors->isNotEmpty() ? $doctors->random()->id : null
                ]
            );
        }

    }
}