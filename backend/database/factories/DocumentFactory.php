<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Document;
use App\Models\User; // For admin_id
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        // Ensure an admin user exists for moderation fields if status is not pending
        $adminUser = User::factory()->admin()->create();

        return [
            'doctor_id' => Doctor::factory(),
            'type' => $this->faker->randomElement(['licence', 'cni', 'diplome', 'autre']),
            'file_path' => 'documents/'.Str::random(10).'.pdf',
            'original_name' => $this->faker->word.'.pdf',
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'rejection_reason' => function (array $attributes) {
                return $attributes['status'] === 'rejected' ? $this->faker->sentence : null;
            },
            'admin_id' => function (array $attributes) use ($adminUser) {
                return in_array($attributes['status'], ['approved', 'rejected']) ? $adminUser->id : null;
            },
            'verified_at' => function (array $attributes) {
                return in_array($attributes['status'], ['approved', 'rejected']) ? $this->faker->dateTimeThisYear : null;
            },
        ];
    }

    public function pending(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
                'admin_id' => null,
                'verified_at' => null,
                'rejection_reason' => null,
            ];
        });
    }

    public function approved(): Factory
    {
        return $this->state(function (array $attributes) {
            $adminUser = User::where('role', 'admin')->first() ?? User::factory()->admin()->create();

            return [
                'status' => 'approved',
                'admin_id' => $adminUser->id,
                'verified_at' => now(),
                'rejection_reason' => null,
            ];
        });
    }

    public function rejected(): Factory
    {
        return $this->state(function (array $attributes) {
            $adminUser = User::where('role', 'admin')->first() ?? User::factory()->admin()->create();

            return [
                'status' => 'rejected',
                'admin_id' => $adminUser->id,
                'verified_at' => now(),
                'rejection_reason' => $this->faker->sentence,
            ];
        });
    }
}
