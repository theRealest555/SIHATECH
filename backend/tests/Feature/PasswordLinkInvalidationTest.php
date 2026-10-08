<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Services\AccountCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordLinkInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public static function profiles(): array
    {
        return [['patient'], ['doctor']];
    }

    private function resetData(User $user, string $token): array
    {
        return ['email' => $user->email, 'token' => $token, 'password' => 'Old-link-overwrite-2026!', 'password_confirmation' => 'Old-link-overwrite-2026!'];
    }

    #[DataProvider('profiles')]
    public function test_profile_password_change_prevents_old_link_overwrite(string $role): void
    {
        $user = User::factory()->create(['role' => $role === 'doctor' ? 'medecin' : 'patient', 'status' => 'actif', 'email_verified_at' => now()]);
        if ($role === 'doctor') {
            Doctor::factory()->create(['user_id' => $user->id]);
        } else {
            Patient::factory()->create(['user_id' => $user->id]);
        }
        $token = Password::createToken($user);
        Sanctum::actingAs($user);
        $this->putJson('/api/'.$role.'/profile/password', ['current_password' => 'password', 'password' => 'Profile-change-2026!', 'password_confirmation' => 'Profile-change-2026!'])->assertOk();
        Auth::forgetGuards();
        $this->postJson('/api/reset-password', $this->resetData($user, $token))->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertTrue(Hash::check('Profile-change-2026!', $user->fresh()->password));
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->assertFalse(Password::tokenExists($user, $token));
    }

    public function test_administrative_reset_invalidates_target_link_but_not_actor_link(): void
    {
        $actor = User::factory()->create(['role' => 'admin', 'status' => 'actif', 'email_verified_at' => now()]);
        Admin::factory()->create(['user_id' => $actor->id, 'admin_status' => 1]);
        $target = User::factory()->create();
        $actorToken = Password::createToken($actor);
        $targetToken = Password::createToken($target);
        Sanctum::actingAs($actor);
        $this->putJson('/api/admin/users/'.$target->id.'/password', ['current_password' => 'password', 'password' => 'Admin-change-2026!', 'password_confirmation' => 'Admin-change-2026!'])->assertOk();
        Auth::forgetGuards();
        $this->postJson('/api/reset-password', $this->resetData($target, $targetToken))->assertUnprocessable();
        $this->assertTrue(Hash::check('Admin-change-2026!', $target->fresh()->password));
        $this->assertTrue(Password::tokenExists($actor, $actorToken));
    }

    public function test_wrong_current_password_does_not_invalidate_recovery(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        try {
            app(AccountCredentials::class)->changePassword($user->id, 'Rejected-change-2026!', 'incorrect');
            $this->fail('Wrong current password should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(Password::tokenExists($user, $token));
            $this->assertTrue(Hash::check('password', $user->fresh()->password));
        }
    }

    public function test_link_deletion_failure_rolls_back_password_tokens_and_auth_version(): void
    {
        $user = User::factory()->create(['remember_token' => 'original-cookie']);
        $token = Password::createToken($user);
        $user->createToken('existing-device');
        DB::listen(function ($query) {
            if (str_starts_with(strtolower($query->sql), 'delete') && str_contains($query->sql, 'password_reset_tokens')) {
                throw new \RuntimeException('Fixture reset-link deletion failure');
            }
        });
        try {
            app(AccountCredentials::class)->changePassword($user->id, 'Failed-change-2026!', 'password');
            $this->fail('A cleanup failure should roll back the credential change.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Fixture reset-link deletion failure', $error->getMessage());
        }
        $user->refresh();
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertSame(0, $user->auth_version);
        $this->assertSame('original-cookie', $user->remember_token);
        $this->assertSame(1, $user->tokens()->count());
        $this->assertTrue(Password::tokenExists($user, $token));
    }

    public function test_new_recovery_link_still_works_after_password_change(): void
    {
        $user = User::factory()->create();
        $old = Password::createToken($user);
        app(AccountCredentials::class)->changePassword($user->id, 'Interim-change-2026!', 'password');
        $user->refresh();
        $fresh = Password::createToken($user);
        $this->assertFalse(Password::tokenExists($user, $old));
        $this->postJson('/api/reset-password', $this->resetData($user, $fresh))->assertOk();
        $this->assertTrue(Hash::check('Old-link-overwrite-2026!', $user->fresh()->password));
        $this->assertFalse(Password::tokenExists($user, $fresh));
        $this->assertSame(2, $user->fresh()->auth_version);
    }
}
