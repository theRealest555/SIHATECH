<?php

namespace Database\Seeders;

use App\Models\Avis;
use App\Models\Patient;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AvisTableSeeder extends Seeder
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

        $avis = [
            [
                'patient_id' => $patients->first()->id,
                'doctor_id' => $doctors->first()->id,
                'note' => 5,
                'commentaire' => 'Excellent médecin, très professionnel et à l\'écoute.',
                'date' => Carbon::now()->subDays(10)
            ],
            [
                'patient_id' => $patients->first()->id,
                'doctor_id' => $doctors->get(1)->id ?? $doctors->first()->id,
                'note' => 4,
                'commentaire' => 'Bon diagnostic mais un peu long en consultation.',
                'date' => Carbon::now()->subDays(5)
            ],
            [
                'patient_id' => $patients->get(1)->id ?? $patients->first()->id,
                'doctor_id' => $doctors->first()->id,
                'note' => 3,
                'commentaire' => 'Consultation correcte mais manque d\'empathie.',
                'date' => Carbon::now()->subDays(3)
            ],
            [
                'patient_id' => $patients->get(2)->id ?? $patients->first()->id,
                'doctor_id' => $doctors->get(1)->id ?? $doctors->first()->id,
                'note' => 2,
                'commentaire' => 'Déçu par la prise en charge, rendez-vous en retard.',
                'date' => Carbon::now()->subDays(1)
            ],
            [
                'patient_id' => $patients->get(3)->id ?? $patients->first()->id,
                'doctor_id' => $doctors->get(2)->id ?? $doctors->first()->id,
                'note' => 1,
                'commentaire' => 'Très mauvaise expérience, je ne recommande pas.',
                'date' => Carbon::today()
            ]
        ];

        foreach ($avis as $avi) {
            Avis::updateOrCreate(
                [
                    'patient_id' => $avi['patient_id'],
                    'doctor_id' => $avi['doctor_id'],
                    'date' => $avi['date']
                ],
                $avi
            );
        }

    }
}