<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AbonnementSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('abonnements')->insert([
            ['doctor_id' => 1, 'plan' => 'mensuel', 'start_date' => now(), 'end_date' => now()->addMonth(), 'status' => 'active'],
            ['doctor_id' => 2, 'plan' => 'mensuel', 'start_date' => now(), 'end_date' => now()->addMonth(), 'status' => 'active'],
            ['doctor_id' => 3, 'plan' => 'annuel', 'start_date' => now(), 'end_date' => now()->addYear(), 'status' => 'active'],
            ['doctor_id' => 4, 'plan' => 'annuel', 'start_date' => now(), 'end_date' => now()->addYear(), 'status' => 'expired'],
        ]);
        
    }
}

