<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileEmailSecurityTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return [['patient'], ['doctor']];
    }

    private function context(string $role): array
    {
        Notification::fake();
        $user = User::factory()->create(['role' => $role === 'doctor' ? 'medecin' : 'patient', 'status' => 'actif', 'email_verified_at' => now()]);
        $data = ['nom' => 'Updated', 'prenom' => 'Profile', 'email' => $user->email];
        if ($role === 'doctor') {
            $doctor = Doctor::factory()->create(['user_id' => $user->id, 'is_verified' => true]);
            $data['speciality_id'] = $doctor->speciality_id;
        } else {
            Patient::factory()->create(['user_id' => $user->id]);
        }
        Sanctum::actingAs($user);

        $data['expected_profile_revision'] = $this->getJson('/api/'.$role.'/profile')->json('profile_revision');

        return [$user, $data, '/api/'.$role.'/profile'];
    }

    #[DataProvider('roles')]
    public function test_email_change_requires_password_and_cannot_modify_profile_on_failure(string $role): void
    {
        [$user, $data, $url] = $this->context($role);
        $data['email'] = 'new@example.test';
        foreach ([null, 'incorrect'] as $password) {
            $this->putJson($url, $data + ['current_password' => $password])->assertUnprocessable()->assertJsonValidationErrors('current_password');
            $this->assertSame($user->email, $user->fresh()->email);
            $this->assertNotSame('Updated', $user->fresh()->nom);
            $this->assertTrue($user->fresh()->hasVerifiedEmail());
        }
        Notification::assertNothingSent();
    }

    #[DataProvider('roles')]
    public function test_new_email_requires_verification_and_old_link_cannot_verify_it(string $role): void
    {
        [$user, $data, $url] = $this->context($role);
        $oldLink = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $data['email'] = 'new@example.test';
        $this->putJson($url, $data + ['current_password' => 'password'])->assertOk()
            ->assertJsonPath('user.email_verified_at', null)->assertJsonPath('email_verification_required', true)
            ->assertJsonPath('email_verification_sent', true);
        Notification::assertSentTo($user->fresh(), VerifyEmailNotification::class, fn ($notification, $channels, $recipient) => $recipient->email === 'new@example.test');
        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/'.$role.'/appointments')->assertStatus(409);
        $this->get($oldLink)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->postJson('/api/email/verification-notification')->assertOk();
        $newLink = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('new@example.test')]);
        $this->get($newLink)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    #[DataProvider('roles')]
    public function test_other_profile_changes_preserve_verification_and_do_not_send_mail(string $role): void
    {
        [$user, $data, $url] = $this->context($role);
        $this->putJson($url, $data)->assertOk()->assertJsonPath('email_verification_required', false);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Notification::assertNothingSent();
    }

    public function test_unverified_doctor_email_hides_public_profile_and_disables_slots(): void
    {
        [$user, $data, $url] = $this->context('doctor');
        $doctor = $user->doctor;
        $data['email'] = 'new@example.test';
        $this->putJson($url, $data + ['current_password' => 'password'])->assertOk();
        $this->getJson('/api/public/doctors/'.$doctor->id)->assertNotFound();
        $this->getJson('/api/public/doctors/'.$doctor->id.'/slots?date='.now()->addWeek()->toDateString())->assertNotFound();
        $this->getJson('/api/public/doctors/'.$doctor->id.'/availability')->assertNotFound();
        $this->assertFalse(Doctor::active()->whereKey($doctor->id)->exists());
    }

    public function test_patient_can_explicitly_clear_preferred_doctor_without_losing_optional_fields(): void
    {
        [$user, $data, $url] = $this->context('patient');
        $doctor = Doctor::factory()->create();
        $user->update(['telephone' => '0612345678']);
        $user->patient->update(['medecin_favori_id' => $doctor->id]);
        $data['expected_profile_revision'] = $this->getJson($url)->json('profile_revision');
        $this->putJson($url, $data + ['medecin_favori_id' => null])->assertOk()->assertJsonPath('patient.medecin_favori_id', null)
            ->assertJsonPath('user.telephone', '0612345678');
    }

    public function test_old_link_cannot_verify_new_address_even_with_stale_request_identity(): void
    {
        [$user, $data, $url] = $this->context('patient');
        $oldLink = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->putJson($url, array_replace($data, ['email' => 'new@example.test', 'current_password' => 'password']))->assertOk();
        // Simulate authentication loading the old identity before the change committed.
        Sanctum::actingAs($user);
        $this->get($oldLink)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
