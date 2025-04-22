<?php

namespace Database\Seeders;

use App\Models\Rendezvous;
use App\Models\Patient;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RendezvousTableSeeder extends Seeder
{
    public function run()
    {
        // Vérifier qu'il y a des patients et des docteurs
        if (Patient::count() === 0 || Doctor::count() === 0) {
            $this->command->error('Veuillez exécuter PatientsTableSeeder et DoctorsTableSeeder d\'abord!');
            return;
        }

        $patients = Patient::all();
        $doctors = Doctor::all();

        $rendezvous = [
            [
                'patient_id' => $patients->first()->id,
                'doctor_id' => $doctors->first()->id,
                'date_heure' => Carbon::now()->addDays(1)->setTime(9, 0),
                'statut' => 'confirmé'
            ],
            [
                'patient_id' => $patients->first()->id,
                'doctor_id' => $doctors->get(1)->id ?? $doctors->first()->id,
                'date_heure' => Carbon::now()->addDays(2)->setTime(10, 30),
                'statut' => 'en_attente'
            ],
            [
                'patient_id' => $patients->get(1)->id ?? $patients->first()->id,
                'doctor_id' => $doctors->first()->id,
                'date_heure' => Carbon::now()->addDays(3)->setTime(14, 0),
                'statut' => 'confirmé'
            ],
            [
                'patient_id' => $patients->get(2)->id ?? $patients->first()->id,
                'doctor_id' => $doctors->get(1)->id ?? $doctors->first()->id,
                'date_heure' => Carbon::now()->addDays(4)->setTime(11, 15),
                'statut' => 'annulé'
            ],
            [
                'patient_id' => $patients->get(3)->id ?? $patients->first()->id,
                'doctor_id' => $doctors->get(2)->id ?? $doctors->first()->id,
                'date_heure' => Carbon::now()->subDays(1)->setTime(16, 30),
                'statut' => 'terminé'
            ]
        ];

        foreach ($rendezvous as $rdv) {
            Rendezvous::updateOrCreate(
                [
                    'patient_id' => $rdv['patient_id'],
                    'doctor_id' => $rdv['doctor_id'],
                    'date_heure' => $rdv['date_heure']
                ],
                $rdv
            );
        }

    }
}