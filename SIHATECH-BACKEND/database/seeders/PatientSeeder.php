<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('role', 'patient')->first();

        DB::table('patients')->insert([
            ['user_id' => 5, 'adresse' => 'Casa', 'medecin_favori_id' => 1],
            ['user_id' => 6, 'adresse' => 'Rabat', 'medecin_favori_id' => 2],
            ['user_id' => 7, 'adresse' => 'Fès', 'medecin_favori_id' => 3],
            ['user_id' => 8, 'adresse' => 'Tanger', 'medecin_favori_id' => 4],
        ]);
        
    }
}
