<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        $this->call([
            // 1. Tables de référence sans dépendances
            SpecialitiesTableSeeder::class,
            LanguagesTableSeeder::class,

            // 2. Utilisateurs de base
            UserTableSeeder::class,

            // 3. Rôles spécifiques
            AdminsTableSeeder::class,
            DoctorsTableSeeder::class,
            PatientsTableSeeder::class,

            // 4. Données opérationnelles
            AbonnementsTableSeeder::class,
            PayementsTableSeeder::class,
            DocumentsTableSeeder::class,
            RendezvousTableSeeder::class,
            AvisTableSeeder::class,
            AuditLogsTableSeeder::class,
        ]);
    }
}
