<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('audit_logs')->insert([
            ['admin_id' => 1, 'action' => 'Validation document 1', 'target_type' => 'Document', 'target_id' => 1],
            ['admin_id' => 2, 'action' => 'Suppression document 2', 'target_type' => 'Document', 'target_id' => 2],
            ['admin_id' => 3, 'action' => 'Modification info doctor', 'target_type' => 'Doctor', 'target_id' => 3],
            ['admin_id' => 4, 'action' => 'Ajout d’un admin', 'target_type' => 'Admin', 'target_id' => 4],
        ]);
        
    }
}

