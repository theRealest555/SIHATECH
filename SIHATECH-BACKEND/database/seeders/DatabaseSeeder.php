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



        $this->call([
            SpecialitySeeder::class,
            LanguageSeeder::class,
            UserSeeder::class,
            DoctorSeeder::class,
            PatientSeeder::class,
            RendezvousSeeder::class,
            AvisSeeder::class,
            AdminSeeder::class,
            AbonnementSeeder::class,
            PayementSeeder::class,
            DocumentSeeder::class,
            AuditLogSeeder::class,
        ]);
    }
}
