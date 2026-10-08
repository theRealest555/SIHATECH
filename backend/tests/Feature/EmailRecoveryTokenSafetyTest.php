<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmailRecoveryTokenSafetyTest extends TestCase
{
    use RefreshDatabase;

    public static function profiles(): array
    {
        return [['patient'], ['doctor']];
    }

    private function context(string $role = 'patient'): array
    {
        Notification::fake();
        $user = User::factory()->create(['role' => $role === 'doctor' ? 'medecin' : 'patient', 'status' => 'actif', 'email_verified_at' => now()]);
        $data = ['nom' => 'Email', 'prenom' => 'Changed', 'email' => 'destination@recovery.test', 'current_password' => 'password'];
        if ($role === 'doctor') {
            $doctor = Doctor::factory()->create(['user_id' => $user->id]);
            $data['speciality_id'] = $doctor->speciality_id;
        } else {
            Patient::factory()->create(['user_id' => $user->id]);
        }
        $old = Password::createToken($user);
        // Represent a historical token left at a currently unowned destination address.
        $destination = clone $user;
        $destination->email = $data['email'];
        $inherited = Password::createToken($destination);
        Sanctum::actingAs($user);
        $url = '/api/'.$role.'/profile';
        $data['expected_profile_revision'] = $this->getJson($url)->json('profile_revision');

        return [$user, $data, $url, $old, $destination, $inherited];
    }

    private function reset(User $user, string $token): array
    {
        return ['email' => $user->email, 'token' => $token, 'password' => 'Recovered-safely-2026!', 'password_confirmation' => 'Recovered-safely-2026!'];
    }

    #[DataProvider('profiles')]
    public function test_address_change_blocks_old_and_inherited_links_but_allows_fresh_recovery(string $role): void
    {
        [$user, $data, $url, $old, $destination, $inherited] = $this->context($role);
        $oldAddress = $user->email;
        $this->putJson($url, $data)->assertOk()->assertJsonPath('email_verification_required', true);
        $this->assertFalse(Password::tokenExists($user, $old));
        $this->assertFalse(Password::tokenExists($destination, $inherited));
        $newOwner = User::factory()->create(['email' => $oldAddress]);
        Auth::forgetGuards();
        $this->postJson('/api/reset-password', $this->reset($newOwner, $old))->assertUnprocessable();
        $this->postJson('/api/reset-password', $this->reset($user->fresh(), $inherited))->assertUnprocessable();
        $this->assertTrue(Hash::check('password', $newOwner->fresh()->password));
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $fresh = Password::createToken($user->fresh());
        $this->postJson('/api/reset-password', $this->reset($user->fresh(), $fresh))->assertOk();
        $this->assertTrue(Hash::check('Recovered-safely-2026!', $user->fresh()->password));
    }

    public function test_unchanged_email_preserves_recovery_and_other_accounts_tokens(): void
    {
        [$user, $data, $url, $old, $destination, $inherited] = $this->context();
        $data['email'] = $user->email;
        unset($data['current_password']);
        $this->putJson($url, $data)->assertOk()->assertJsonPath('email_verification_required', false);
        $this->assertTrue(Password::tokenExists($user, $old));
        $this->assertTrue(Password::tokenExists($destination, $inherited));
        Notification::assertNothingSent();
    }

    public function test_rejected_current_password_preserves_both_tokens_and_profile(): void
    {
        [$user, $data, $url, $old, $destination, $inherited] = $this->context();
        $data['current_password'] = 'incorrect';
        $this->putJson($url, $data)->assertUnprocessable();
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertTrue(Password::tokenExists($user, $old));
        $this->assertTrue(Password::tokenExists($destination, $inherited));
        Notification::assertNothingSent();
    }

    public function test_profile_save_failure_restores_both_tokens_and_verification(): void
    {
        [$user, $data, $url, $old, $destination, $inherited] = $this->context();
        User::saving(function ($saving) use ($data) {
            if ($saving->email === $data['email']) {
                throw new \RuntimeException('Fixture profile save failure');
            }
        });
        try {
            $this->putJson($url, $data)->assertStatus(500);
            $this->assertSame($user->email, $user->fresh()->email);
            $this->assertTrue($user->fresh()->hasVerifiedEmail());
            $this->assertTrue(Password::tokenExists($user, $old));
            $this->assertTrue(Password::tokenExists($destination, $inherited));
            Notification::assertNothingSent();
        } finally {
            User::flushEventListeners();
        }
    }

    public function test_destination_token_delete_failure_restores_old_token_and_profile(): void
    {
        [$user, $data, $url, $old, $destination, $inherited] = $this->context();
        DB::listen(function ($query) use ($data) {
            if (str_starts_with(strtolower($query->sql), 'delete') && str_contains($query->sql, 'password_reset_tokens') && in_array($data['email'], $query->bindings, true)) {
                throw new \RuntimeException('Fixture destination token failure');
            }
        });
        $this->putJson($url, $data)->assertStatus(500);
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertTrue(Password::tokenExists($user, $old));
        $this->assertTrue(Password::tokenExists($destination, $inherited));
        Notification::assertNothingSent();
    }
}
