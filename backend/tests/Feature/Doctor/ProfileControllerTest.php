<?php

namespace Tests\Feature\Doctor;

use App\Models\Doctor;
use App\Models\Speciality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctorUser;

    protected Doctor $doctor;

    protected Speciality $speciality;

    protected function setUp(): void
    {
        parent::setUp();
        $this->doctorUser = User::factory()->create(['role' => 'medecin', 'email_verified_at' => now(), 'status' => 'actif']);
        $this->speciality = Speciality::factory()->create();
        $this->doctor = Doctor::factory()->create([
            'user_id' => $this->doctorUser->id,
            'speciality_id' => $this->speciality->id,
        ]);
        Sanctum::actingAs($this->doctorUser, ['role:medecin']);
    }

    public function test_doctor_can_view_their_profile()
    {
        $response = $this->getJson('/api/doctor/profile'); //

        $response->assertStatus(200)
            ->assertJsonPath('user.id', $this->doctorUser->id)
            ->assertJsonPath('doctor.id', $this->doctor->id)
            ->assertJsonPath('doctor.speciality.id', $this->speciality->id);
    }

    public function test_doctor_can_update_their_profile()
    {
        $newSpeciality = Speciality::factory()->create();
        $updateData = [
            'nom' => 'UpdatedDoctorName',
            'prenom' => 'TestDoctor',
            'current_password' => 'password',
            'email' => 'updated.doctor@example.com',
            'telephone' => '0699887766',
            'adresse' => '456 Clinic Ave',
            'sexe' => 'homme',
            'date_de_naissance' => '1980-01-01',
            'speciality_id' => $newSpeciality->id,
            'description' => 'Experienced general practitioner.',
            'horaires' => json_encode(['lundi' => ['09:00-17:00']]),
        ];

        $updateData['expected_profile_revision'] = $this->getJson('/api/doctor/profile')->json('profile_revision');
        $response = $this->putJson('/api/doctor/profile', $updateData); //

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Profile updated successfully')
            ->assertJsonPath('user.nom', 'UpdatedDoctorName')
            ->assertJsonPath('doctor.speciality_id', $newSpeciality->id)
            ->assertJsonPath('doctor.description', 'Experienced general practitioner.');

        $this->assertDatabaseHas('users', ['id' => $this->doctorUser->id, 'nom' => 'UpdatedDoctorName']);
        $this->assertDatabaseHas('doctors', ['user_id' => $this->doctorUser->id, 'speciality_id' => $newSpeciality->id]);
    }

    public function test_doctor_can_update_their_password()
    {
        $currentPassword = 'currentPassword123';
        $this->doctorUser->update(['password' => Hash::make($currentPassword)]);

        $passwordData = [
            'current_password' => $currentPassword,
            'password' => 'newDoctorPass!@#',
            'password_confirmation' => 'newDoctorPass!@#',
        ];

        $response = $this->putJson('/api/doctor/profile/password', $passwordData); //

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Password updated successfully');
        $this->assertTrue(Hash::check('newDoctorPass!@#', $this->doctorUser->fresh()->password));
    }

    public function test_doctor_can_update_profile_photo()
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('doctor_id.jpg');

        $response = $this->postJson('/api/doctor/profile/photo', ['photo' => $file]); //

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Photo updated successfully')
            ->assertJsonStructure(['photo_url']);

        $newPath = User::find($this->doctorUser->id)->photo;
        $this->assertTrue(Storage::disk('public')->exists($newPath));
    }

    public function test_doctor_can_complete_profile_after_social_auth()
    {
        // Simulate a doctor user who has just registered via social auth
        // and needs to complete their profile (e.g., speciality_id is missing)
        $socialDoctorUser = User::factory()->create([
            'role' => 'medecin',
            'email_verified_at' => now(),
            'status' => 'actif',
            'telephone' => null, // Ensure some fields are nullable to be completed
        ]);
        $doctorProfile = Doctor::factory()->create([
            'user_id' => $socialDoctorUser->id,
            'speciality_id' => null, // Mark as needing completion
            'description' => null,
        ]);

        Sanctum::actingAs($socialDoctorUser); // Act as this new doctor

        $newSpeciality = Speciality::factory()->create();
        $completionData = [
            'speciality_id' => $newSpeciality->id,
            'telephone' => '0612345678',
            'adresse' => '123 Clinic Street',
            'sexe' => 'homme',
            'date_de_naissance' => '1985-07-15',
        ];

        $completionData['expected_profile_revision'] = $this->getJson('/api/doctor/profile')->json('profile_revision');
        $response = $this->postJson('/api/doctor/complete-profile', $completionData); //

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Profile completed successfully')
            ->assertJsonPath('user.doctor.speciality_id', $newSpeciality->id)
            ->assertJsonPath('user.telephone', '0612345678');

        $this->assertDatabaseHas('doctors', [
            'user_id' => $socialDoctorUser->id,
            'speciality_id' => $newSpeciality->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $socialDoctorUser->id,
            'telephone' => '0612345678',
        ]);
    }
}
