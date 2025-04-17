<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PayementSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('payements')->insert([
            ['doctor_id' => 1, 'abonnement_id' => 1, 'montant' => 199.99, 'methode' => 'cmi', 'date' => now()],
            ['doctor_id' => 2, 'abonnement_id' => 2, 'montant' => 199.99, 'methode' => 'paypal', 'date' => now()],
            ['doctor_id' => 3, 'abonnement_id' => 3, 'montant' => 999.99, 'methode' => 'cmi', 'date' => now()],
            ['doctor_id' => 4, 'abonnement_id' => 4, 'montant' => 999.99, 'methode' => 'paypal', 'date' => now()],
        ]);
        
    }
}

