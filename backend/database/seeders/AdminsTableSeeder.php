<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminsTableSeeder extends Seeder
{
    public function run()
    {
        $admins = [
            [
                'nom' => 'Admin',
                'prenom' => 'System',
                'email' => 'admin@medicall.com',
                'password' => Hash::make('Admin123!'),
                'role' => 'admin',
                'telephone' => '0612345678',
                'sexe' => 'homme',
                'date_de_naissance' => '1980-01-01'
            ],
            [
                'nom' => 'Responsable',
                'prenom' => 'Application',
                'email' => 'responsable@medicall.com',
                'password' => Hash::make('Admin123!'),
                'role' => 'admin',
                'telephone' => '0698765432',
                'sexe' => 'femme',
                'date_de_naissance' => '1985-05-15'
            ]
        ];

        foreach ($admins as $adminData) {
            $user = User::updateOrCreate(
                ['email' => $adminData['email']],
                $adminData
            );

            Admin::updateOrCreate(
                ['user_id' => $user->id]
            );
        }

    }
}