<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserTableSeeder extends Seeder
{
    public function run()
    {
        // Désactiver les contraintes de clé étrangère temporairement
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        User::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $users = [
            // Administrateur
            [
                'nom' => 'Admin',
                'prenom' => 'System',
                'username' => 'admin_sys',
                'email' => 'admin@medicall.com',
                'email_verified_at' => Carbon::now(),
                'telephone' => '0612345678',
                'password' => Hash::make('Admin123!'),
                'photo' => 'users/admin.jpg',
                'adresse' => '123 Rue Admin, 75001 Paris',
                'sexe' => 'homme',
                'date_de_naissance' => '1980-01-01',
                'role' => 'admin',
                'status' => 'actif',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],

            // Médecins
            [
                'nom' => 'Dupont',
                'prenom' => 'Jean',
                'username' => 'dr_dupont',
                'email' => 'j.dupont@medicall.com',
                'email_verified_at' => Carbon::now(),
                'telephone' => '0698765432',
                'password' => Hash::make('Medecin123!'),
                'photo' => 'users/medecins/jean.jpg',
                'adresse' => '45 Rue des Médecins, 69000 Lyon',
                'sexe' => 'homme',
                'date_de_naissance' => '1975-05-15',
                'role' => 'medecin',
                'status' => 'actif',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],

            [
                'nom' => 'Martin',
                'prenom' => 'Sophie',
                'username' => 'dr_martin',
                'email' => 's.martin@medicall.com',
                'email_verified_at' => Carbon::now(),
                'telephone' => '0687654321',
                'password' => Hash::make('Medecin123!'),
                'photo' => 'users/medecins/sophie.jpg',
                'adresse' => '12 Avenue de la Santé, 13000 Marseille',
                'sexe' => 'femme',
                'date_de_naissance' => '1982-08-22',
                'role' => 'medecin',
                'status' => 'actif',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],

            // Patients
            [
                'nom' => 'Bernard',
                'prenom' => 'Alice',
                'username' => 'alice_b',
                'email' => 'a.bernard@patient.com',
                'email_verified_at' => Carbon::now(),
                'telephone' => '0678912345',
                'password' => Hash::make('Patient123!'),
                'photo' => 'users/patients/alice.jpg',
                'adresse' => '78 Rue des Patients, 31000 Toulouse',
                'sexe' => 'femme',
                'date_de_naissance' => '1990-11-30',
                'role' => 'patient',
                'status' => 'actif',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],

            [
                'nom' => 'Petit',
                'prenom' => 'Thomas',
                'username' => 'thomas_p',
                'email' => 't.petit@patient.com',
                'email_verified_at' => Carbon::now(),
                'telephone' => '0654321897',
                'password' => Hash::make('Patient123!'),
                'photo' => 'users/patients/thomas.jpg',
                'adresse' => '32 Boulevard de la Santé, 59000 Lille',
                'sexe' => 'homme',
                'date_de_naissance' => '1988-04-12',
                'role' => 'patient',
                'status' => 'actif',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],

            // Patient inactif
            [
                'nom' => 'Durand',
                'prenom' => 'Claire',
                'username' => 'claire_d',
                'email' => 'c.durand@patient.com',
                'email_verified_at' => Carbon::now(),
                'telephone' => '0699887766',
                'password' => Hash::make('Patient123!'),
                'photo' => 'users/patients/claire.jpg',
                'adresse' => '19 Rue Inactive, 44000 Nantes',
                'sexe' => 'femme',
                'date_de_naissance' => '1995-07-18',
                'role' => 'patient',
                'status' => 'inactif',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],

            // Patient en attente
            [
                'nom' => 'Leroy',
                'prenom' => 'Marc',
                'username' => 'marc_l',
                'email' => 'm.leroy@patient.com',
                'email_verified_at' => null,
                'telephone' => '0633445566',
                'password' => Hash::make('Patient123!'),
                'photo' => null,
                'adresse' => '5 Rue En Attente, 67000 Strasbourg',
                'sexe' => 'homme',
                'date_de_naissance' => '1992-02-25',
                'role' => 'patient',
                'status' => 'en_attente',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                $user
            );
        }

    }
}