<?php

namespace Database\Seeders;

use App\Models\Abonnement;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AbonnementsTableSeeder extends Seeder
{
    public function run()
    {
        if (Doctor::count() === 0) {
            $this->call(DoctorsTableSeeder::class);
        }

        $doctors = Doctor::all();
        
        // Utilisez exactement les mêmes valeurs que dans votre migration
        $plans = ['mensuel', 'semi-annel', 'annuel']; // Changé de 'semi-annuel' à 'semestriel'
        $statuses = ['active', 'expired', 'canceled'];

        $abonnements = [
            [
                'doctor_id' => $doctors->first()->id,
                'plan' => 'annuel',
                'start_date' => Carbon::now()->subYear(),
                'end_date' => Carbon::now()->subMonth(),
                'status' => 'expired'
            ],
            [
                'doctor_id' => $doctors->first()->id,
                'plan' => 'mensuel',
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonth(),
                'status' => 'active'
            ],
            [
                'doctor_id' => $doctors->count() > 1 ? $doctors[1]->id : $doctors->first()->id,
                'plan' => 'semi-annel', // Modifié ici
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(6),
                'status' => 'active'
            ],
            [
                'doctor_id' => $doctors->count() > 2 ? $doctors[2]->id : $doctors->first()->id,
                'plan' => 'annuel',
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addYear(),
                'status' => 'active'
            ]
        ];

        foreach ($abonnements as $abonnement) {
            Abonnement::updateOrCreate(
                [
                    'doctor_id' => $abonnement['doctor_id'],
                    'start_date' => $abonnement['start_date']
                ],
                $abonnement
            );
        }

    }
}