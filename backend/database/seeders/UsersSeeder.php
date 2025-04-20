<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Médecin
        DB::table('users')->insert([
            ['nom' => 'Driss', 'prenom' => 'Ahmed', 'email' => 'med1@mail.com', 'telephone' => '0600000001', 'mot_de_passe' => bcrypt('pass'), 'role' => 'medecin'],
            ['nom' => 'Lamia', 'prenom' => 'Youssef', 'email' => 'med2@mail.com', 'telephone' => '0600000002', 'mot_de_passe' => bcrypt('pass'), 'role' => 'medecin'],
            ['nom' => 'Samira', 'prenom' => 'Ali', 'email' => 'med3@mail.com', 'telephone' => '0600000003', 'mot_de_passe' => bcrypt('pass'), 'role' => 'medecin'],
            ['nom' => 'Nabil', 'prenom' => 'Kamal', 'email' => 'med4@mail.com', 'telephone' => '0600000004', 'mot_de_passe' => bcrypt('pass'), 'role' => 'medecin'],
        ]);

        // Patient
        DB::table('users')->insert([
            ['nom' => 'Fatima', 'prenom' => 'Saad', 'email' => 'pat1@mail.com', 'telephone' => '0600000005', 'mot_de_passe' => bcrypt('pass'), 'role' => 'patient'],
            ['nom' => 'Karim', 'prenom' => 'Imane', 'email' => 'pat2@mail.com', 'telephone' => '0600000006', 'mot_de_passe' => bcrypt('pass'), 'role' => 'patient'],
            ['nom' => 'Omar', 'prenom' => 'Lina', 'email' => 'pat3@mail.com', 'telephone' => '0600000007', 'mot_de_passe' => bcrypt('pass'), 'role' => 'patient'],
            ['nom' => 'Noura', 'prenom' => 'Rachid', 'email' => 'pat4@mail.com', 'telephone' => '0600000008', 'mot_de_passe' => bcrypt('pass'), 'role' => 'patient'],
        ]);

        // Admin
        DB::table('users')->insert([
            ['nom' => 'Admin1', 'prenom' => 'Super', 'email' => 'admin1@mail.com', 'telephone' => '0600000009', 'mot_de_passe' => bcrypt('admin123'), 'role' => 'admin'],
            ['nom' => 'Admin2', 'prenom' => 'Super', 'email' => 'admin2@mail.com', 'telephone' => '0600000010', 'mot_de_passe' => bcrypt('admin123'), 'role' => 'admin'],
            ['nom' => 'Admin3', 'prenom' => 'Super', 'email' => 'admin3@mail.com', 'telephone' => '0600000011', 'mot_de_passe' => bcrypt('admin123'), 'role' => 'admin'],
            ['nom' => 'Admin4', 'prenom' => 'Super', 'email' => 'admin4@mail.com', 'telephone' => '0600000012', 'mot_de_passe' => bcrypt('admin123'), 'role' => 'admin'],
        ]);
    }
}

