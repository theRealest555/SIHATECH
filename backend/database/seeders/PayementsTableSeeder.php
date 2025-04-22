<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payement;
use App\Models\Doctor;
use App\Models\Abonnement;
use Carbon\Carbon;

class PayementsTableSeeder extends Seeder
{
    public function run()
    {
        $abonnements = Abonnement::limit(4)->get();

        foreach ($abonnements as $abonnement) {
            Payement::create([
                'doctor_id' => $abonnement->doctor_id,
                'abonnement_id' => $abonnement->id,
                'montant' => $abonnement->plan === 'mensuel' ? 100 : ($abonnement->plan === 'semi-annel' ? 500 : 900),
                'methode' => 'cmi',
                'date' => Carbon::now()
            ]);
        }
    }
}