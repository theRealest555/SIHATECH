<?php

namespace Database\Factories;

use App\Models\Avis;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Rendezvous; // Ensure Doctor model is imported if used explicitly
use App\Models\User; // Ensure Patient model is imported
use Illuminate\Database\Eloquent\Factories\Factory;

class AvisFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Avis::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Ensure patient and doctor users exist or create them
        $patientUser = User::factory()->create(['role' => 'patient']);
        // Ensure the patient has a patient profile record
        if (! $patientUser->patient) {
            Patient::factory()->create(['user_id' => $patientUser->id]); // Add this block
        }
        // Refresh the $patientUser to get the loaded relationship
        $patientUser->refresh();

        $doctorUser = User::factory()->create(['role' => 'medecin']);
        // Ensure the doctor has a doctor profile record
        if (! $doctorUser->doctor) {
            // Using \App\Models\Doctor::factory() or just Doctor::factory() if imported
            Doctor::factory()->create(['user_id' => $doctorUser->id]);
        }
        // Refresh the $doctorUser to get the loaded relationship
        $doctorUser->refresh();

        // Create an appointment for context, or handle null rendezvous_id
        // Ensure that $patientUser->patient and $doctorUser->doctor are not null
        // The refresh above should help, but an extra check or direct creation might be safer
        // For RendezvousFactory, it also creates users, so we use the users created here.

        $rendezvous = Rendezvous::factory()->create([
            'patient_id' => $patientUser->patient->id, // Now $patientUser->patient should exist
            'statut' => 'terminé',
            'date_heure' => now()->subDay(),
            'doctor_id' => $doctorUser->doctor->id,   // $doctorUser->doctor exists due to the check
        ]);

        return [
            'patient_id' => $patientUser->id, // patient_id in reviews table refers to users table id
            'doctor_id' => $doctorUser->id,   // doctor_id in reviews table refers to users table id
            'rendezvous_id' => $rendezvous->id,
            'rating' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->paragraph,
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'moderated_at' => null,
            'moderated_by' => null,
        ];
    }

    // ... rest of the factory states (pending, approved, rejected)
    /**
     * Indicate that the review is pending.
     *
     * @return Factory
     */
    public function pending()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
            ];
        });
    }

    /**
     * Indicate that the review is approved.
     *
     * @return Factory
     */
    public function approved()
    {
        return $this->state(function (array $attributes) {
            // Ensure an admin user exists or create one
            $adminUser = User::where('role', 'admin')->first() ?? User::factory()->admin()->create();

            return [
                'status' => 'approved',
                'moderated_at' => now(),
                'moderated_by' => $adminUser->id, // Assign a moderator
            ];
        });
    }

    /**
     * Indicate that the review is rejected.
     *
     * @return Factory
     */
    public function rejected()
    {
        return $this->state(function (array $attributes) {
            // Ensure an admin user exists or create one
            $adminUser = User::where('role', 'admin')->first() ?? User::factory()->admin()->create();

            return [
                'status' => 'rejected',
                'moderated_at' => now(),
                'moderated_by' => $adminUser->id, // Assign a moderator
            ];
        });
    }
}
