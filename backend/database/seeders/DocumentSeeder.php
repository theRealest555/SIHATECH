<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('documents')->insert([
            ['doctor_id' => 1, 'type' => 'licence', 'file_path' => 'C:\Users\Dell\Desktop\m206cloud\cloud_course.pdf', 'status' => 'approved'],
            ['doctor_id' => 2, 'type' => 'cni', 'file_path' => 'cni2.pdf', 'status' => 'pending'],
            ['doctor_id' => 3, 'type' => 'diplome', 'file_path' => 'diplome3.pdf', 'status' => 'approved'],
            ['doctor_id' => 4, 'type' => 'licence', 'file_path' => 'licence4.pdf', 'status' => 'rejected'],
        ]);
    }
}

