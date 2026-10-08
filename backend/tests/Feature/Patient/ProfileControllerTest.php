<?php

namespace Tests\Feature\Patient;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase; // Added for tearDown

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $patientUser;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure Storage is faked for tests involving file uploads
        Storage::fake('public');

        $this->patientUser = User::factory()->create(['role' => 'patient', 'email_verified_at' => now(), 'status' => 'actif']);
        $this->patient = Patient::factory()->create(['user_id' => $this->patientUser->id]);
        Sanctum::actingAs($this->patientUser, ['role:patient']);
    }

    // Add this tearDown method to the class
    protected function tearDown(): void
    {
        // Attempt to roll back any lingering transactions if they exist
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_patient_can_view_their_profile()
    {
        $response = $this->getJson('/api/patient/profile');

        $response->assertStatus(200)
            ->assertJsonPath('user.id', $this->patientUser->id)
            ->assertJsonPath('patient.id', $this->patient->id);
    }

    public function test_patient_can_update_their_profile()
    {
        $doctor = Doctor::factory()->create();
        $updateData = [
            'nom' => 'UpdatedLastName',
            'prenom' => 'UpdatedFirstName',
            'current_password' => 'password',
            'email' => 'updated.patient@example.com',
            'telephone' => '0611223345',
            'adresse' => '123 Updated St',
            'sexe' => 'femme',
            'date_de_naissance' => '1995-05-05',
            'medecin_favori_id' => $doctor->id,
        ];

        $updateData['expected_profile_revision'] = $this->getJson('/api/patient/profile')->json('profile_revision');
        $response = $this->putJson('/api/patient/profile', $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Profile updated successfully')
            ->assertJsonPath('user.nom', 'UpdatedLastName')
            ->assertJsonPath('user.email', 'updated.patient@example.com')
            ->assertJsonPath('patient.medecin_favori_id', $doctor->id);

        $this->assertDatabaseHas('users', ['id' => $this->patientUser->id, 'nom' => 'UpdatedLastName', 'email' => 'updated.patient@example.com']);
        $this->assertDatabaseHas('patients', ['user_id' => $this->patientUser->id, 'medecin_favori_id' => $doctor->id]);
    }

    public function test_patient_cannot_update_profile_with_invalid_data()
    {
        $response = $this->putJson('/api/patient/profile', ['email' => 'not-an-email']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_patient_can_update_their_password()
    {
        $passwordData = [
            'current_password' => 'password', // Default factory password
            'password' => 'newSecurePassword123',
            'password_confirmation' => 'newSecurePassword123',
        ];

        // Ensure the user's current password is 'password'
        $this->patientUser->update(['password' => Hash::make('password')]);

        $response = $this->putJson('/api/patient/profile/password', $passwordData);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Password updated successfully');

        $this->assertTrue(Hash::check('newSecurePassword123', $this->patientUser->fresh()->password));
    }

    public function test_patient_cannot_update_password_with_incorrect_current_password()
    {
        // Ensure the user's current password is 'password'
        $this->patientUser->update(['password' => Hash::make('password')]);

        $passwordData = [
            'current_password' => 'wrongOldPassword',
            'password' => 'newSecurePassword123',
            'password_confirmation' => 'newSecurePassword123',
        ];
        $response = $this->putJson('/api/patient/profile/password', $passwordData);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'The current password is incorrect.');
    }

    public function test_patient_can_update_profile_photo()
    {
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->postJson('/api/patient/profile/photo', ['photo' => $file]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Photo updated successfully')
            ->assertJsonStructure(['photo_url', 'path']);

        $path = $response->json('path');
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertDatabaseHas('users', ['id' => $this->patientUser->id, 'photo' => $path]);
    }

    public function test_doctor_cannot_access_patient_profile_routes()
    {
        $doctorUser = User::factory()->create(['role' => 'medecin']);
        Doctor::factory()->create(['user_id' => $doctorUser->id]);
        Sanctum::actingAs($doctorUser, ['role:medecin']);

        $response = $this->getJson('/api/patient/profile');
        $response->assertStatus(403); // Forbidden
    }
}
