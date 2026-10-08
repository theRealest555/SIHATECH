<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Rendezvous; // Import Patient model
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class RendezvousFactory extends Factory
{
    protected $model = Rendezvous::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'date_heure' => Carbon::instance($this->faker->dateTimeBetween('+1 day', '+1 month')),
            'statut' => $this->faker->randomElement(['confirmé', 'en_attente', 'annulé', 'terminé', 'no_show']),
        ];
    }
}
