<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Admin;
use App\Models\Doctor;
use Illuminate\Database\Seeder;

class AuditLogsTableSeeder extends Seeder
{
    public function run()
    {
        // Vérifier les dépendances
        if (Admin::count() === 0 || Doctor::count() === 0) {
            $this->command->error('Veuillez exécuter AdminsTableSeeder et DoctorsTableSeeder d\'abord!');
            return;
        }

        $admins = Admin::limit(4)->get();
        $doctors = Doctor::limit(2)->get();

        $actions = [
            'doctor_verification',
            'document_approval',
            'profile_update',
            'account_suspension'
        ];

        foreach ($admins as $admin) {
            foreach ($doctors as $doctor) {
                AuditLog::create([
                    'user_id' => $admin->id,
                    'action' => $actions[array_rand($actions)],
                    'target_type' => Doctor::class,
                    'target_id' => $doctor->id,
                ]);
            }
        }

    }
}