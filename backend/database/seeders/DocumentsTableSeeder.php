<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Document;
use App\Models\Doctor;
use App\Models\User;

class DocumentsTableSeeder extends Seeder
{
    public function run()
    {
        $doctors = Doctor::limit(4)->get();
        $admin = User::where('role', 'admin')->first();

        foreach ($doctors as $doctor) {
            Document::create([
                'doctor_id' => $doctor->id,
                'type' => 'licence',
                'file_path' => 'documents/licence_'.$doctor->id.'.pdf',
                'original_name' => 'licence.pdf',
                'status' => 'approved',
                'admin_id' => $admin->id,
                'verified_at' => now()
            ]);

            Document::create([
                'doctor_id' => $doctor->id,
                'type' => 'cni',
                'file_path' => 'documents/cni_'.$doctor->id.'.pdf',
                'original_name' => 'cni.pdf',
                'status' => 'approved'
            ]);
        }
    }
}