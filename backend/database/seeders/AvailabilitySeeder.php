<?php

namespace Database\Seeders;

use App\Models\Availability;
use Illuminate\Database\Seeder;

class AvailabilitySeeder extends Seeder
{
    public function run()
    {
        $doctors = [1, 2, 3, 4];
        foreach ($doctors as $doctorId) {
            Availability::updateOrCreate(
                ['doctor_id' => $doctorId, 'day_of_week' => 'friday'],
                ['time_range' => '09:00-17:00', 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}