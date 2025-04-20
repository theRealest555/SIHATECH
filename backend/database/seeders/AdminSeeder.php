<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('admins')->insert([
            ['user_id' => 9, 'username' => 'admin1', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 10, 'username' => 'admin2', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 11, 'username' => 'admin3', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 12, 'username' => 'admin4', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
