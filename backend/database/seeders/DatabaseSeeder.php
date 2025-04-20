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
            SpecialitiesSeeder::class,
            LanguagesSeeder::class,
            UsersSeeder::class,
            DoctorsSeeder::class,
            PatientsSeeder::class,
            RendezVousSeeder::class,
            AdminSeeder::class,
            AbonnementSeeder::class,
            PayementSeeder::class,
            DocumentSeeder::class,
            AuditLogSeeder::class,
        ]);
    }
}
