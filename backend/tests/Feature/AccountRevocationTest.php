<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Services\AccountCredentials;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountRevocationTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return [['patient'], ['doctor']];
    }

    private function account(string $role = 'patient'): User
    {
        $user = User::factory()->create(['role' => $role === 'doctor' ? 'medecin' : $role, 'status' => 'actif', 'email_verified_at' => now(), 'remember_token' => 'old-remember-token']);
        if ($role === 'patient') {
            Patient::factory()->create(['user_id' => $user->id]);
        } elseif ($role === 'doctor') {
            Doctor::factory()->create(['user_id' => $user->id]);
        } else {
            Admin::factory()->create(['user_id' => $user->id, 'admin_status' => 1]);
        }

        return $user;
    }

    private function marker(User $user): array
    {
        return ['user_id' => $user->id, 'version' => $user->auth_version];
    }

    private function browser(User $user, ?array $marker): self
    {
        Auth::forgetGuards();
        $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000')
            ->withSession([AccountCredentials::SESSION_KEY => $marker, 'password_hash_web' => null]);

        return $this;
    }

    private function passwordData(string $current = 'password'): array
    {
        return ['current_password' => $current, 'password' => 'New-secure-password-2026!', 'password_confirmation' => 'New-secure-password-2026!'];
    }

    #[DataProvider('roles')]
    public function test_browser_password_change_keeps_current_session_and_revokes_tokens_and_other_sessions(string $role): void
    {
        $user = $this->account($role);
        $oldMarker = $this->marker($user);
        $user->createToken('phone');
        $user->createToken('desktop');
        $this->browser($user, $oldMarker)->putJson('/api/'.$role.'/profile/password', $this->passwordData())
            ->assertOk()->assertJsonPath('reauthentication_required', false)
            ->assertJsonPath('api_tokens_revoked', true)->assertSessionHas(AccountCredentials::SESSION_KEY, ['user_id' => $user->id, 'version' => 1]);
        $user->refresh();
        $this->assertTrue(Hash::check('New-secure-password-2026!', $user->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotSame('old-remember-token', $user->remember_token);
        $this->browser($user, $this->marker($user))->getJson('/api/user')->assertOk()->assertJsonMissingPath('user.auth_version');
        $this->browser($user, $oldMarker)->getJson('/api/user')->assertUnauthorized();
    }

    #[DataProvider('roles')]
    public function test_incorrect_password_does_not_revoke_existing_credentials(string $role): void
    {
        $user = $this->account($role);
        $user->createToken('phone');
        Sanctum::actingAs($user);
        $this->putJson('/api/'.$role.'/profile/password', $this->passwordData('wrong'))->assertUnprocessable();
        $this->assertSame(0, $user->fresh()->auth_version);
        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame('old-remember-token', $user->fresh()->remember_token);
    }

    public function test_bearer_password_change_revokes_the_token_used_to_make_the_request(): void
    {
        $user = $this->account();
        $token = $user->createToken('phone')->plainTextToken;
        $this->withToken($token)->putJson('/api/patient/profile/password', $this->passwordData())
            ->assertOk()->assertJsonPath('reauthentication_required', true);
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_password_reset_revokes_all_access_and_legacy_unstamped_sessions(): void
    {
        $user = $this->account();
        $user->createToken('phone');
        $token = Password::createToken($user);
        $this->postJson('/api/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'Reset-password-2026!', 'password_confirmation' => 'Reset-password-2026!',
        ])->assertOk();
        $user->refresh();
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, $user->auth_version);
        $this->assertTrue(Hash::check('Reset-password-2026!', $user->password));
        $this->browser($user, null)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_legacy_session_can_bootstrap_before_the_first_security_change(): void
    {
        $user = $this->account();
        $this->browser($user, null)->getJson('/api/user')->assertOk()->assertSessionHas(AccountCredentials::SESSION_KEY, $this->marker($user));
    }

    public function test_fresh_login_stamps_the_current_auth_version(): void
    {
        $user = $this->account();
        app(AccountCredentials::class)->changePassword($user->id, 'New-login-password!');
        $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', ['email' => $user->email, 'password' => 'New-login-password!'])
            ->assertOk()->assertSessionHas(AccountCredentials::SESSION_KEY, ['user_id' => $user->id, 'version' => 1]);
    }

    public function test_suspension_and_reactivation_do_not_restore_old_sessions_or_tokens(): void
    {
        $admin = $this->account('admin');
        $target = $this->account();
        $oldMarker = $this->marker($target);
        $target->createToken('phone');
        Sanctum::actingAs($admin);
        $this->putJson('/api/admin/users/'.$target->id.'/status', $this->statusDecision($target, ['status' => 'inactif']))->assertOk();
        $this->assertSame(0, $target->tokens()->count());
        $this->putJson('/api/admin/users/'.$target->id.'/status', $this->statusDecision($target, ['status' => 'actif']))->assertOk();
        $this->assertSame(1, $target->fresh()->auth_version);
        $this->browser($target->fresh(), $oldMarker)->getJson('/api/user')->assertUnauthorized();
        $this->assertDatabaseHas('audit_logs', ['action' => 'updated_user_status', 'target_id' => $target->id, 'user_id' => $admin->id]);
    }

    public function test_admin_demotion_revokes_access_even_when_approval_is_restored(): void
    {
        $admin = $this->account('admin');
        $target = $this->account('admin');
        $oldMarker = $this->marker($target);
        $target->createToken('admin-phone');
        Sanctum::actingAs($admin);
        $this->putJson('/api/admin/admins/'.$target->admin->id.'/status', $this->statusDecision($target, ['admin_status' => 0]))->assertOk();
        $this->putJson('/api/admin/admins/'.$target->admin->id.'/status', $this->statusDecision($target, ['admin_status' => 1]))->assertOk();
        $this->assertSame(0, $target->tokens()->count());
        $this->browser($target->fresh(), $oldMarker)->getJson('/api/admin/dashboard')->assertUnauthorized();
    }

    public function test_admin_password_reset_is_atomic_with_the_audit_log(): void
    {
        $admin = $this->account('admin');
        $target = $this->account();
        $recoveryToken = Password::createToken($target);
        $target->createToken('phone');
        Sanctum::actingAs($admin);
        Event::listen('eloquent.creating: '.AuditLog::class, fn () => throw new \RuntimeException('Audit unavailable'));
        $this->putJson('/api/admin/users/'.$target->id.'/password', ['password' => 'Admin-reset-2026!', 'password_confirmation' => 'Admin-reset-2026!', 'current_password' => 'password'])->assertStatus(500);
        $this->assertSame(1, $target->tokens()->count());
        $this->assertSame(0, $target->fresh()->auth_version);
        $this->assertTrue(Hash::check('password', $target->fresh()->password));
        $this->assertTrue(Password::tokenExists($target, $recoveryToken));
    }

    public function test_login_cannot_issue_a_token_from_credentials_read_before_a_password_reset(): void
    {
        $user = $this->account();
        Event::listen(Login::class, fn () => app(AccountCredentials::class)->changePassword($user->id, 'Concurrent-reset-password!'));
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_admin_login_cannot_issue_a_token_after_concurrent_demotion(): void
    {
        $user = $this->account('admin');
        Event::listen(Login::class, function () use ($user) {
            $user->admin->update(['admin_status' => 0]);
        });
        $this->postJson('/api/admin/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_reset_cannot_change_password_after_the_account_email_changed(): void
    {
        $user = $this->account();
        $originalEmail = $user->email;
        $user->update(['email' => 'replacement@example.test']);
        try {
            app(AccountCredentials::class)->changePassword($user->id, 'Reset-password!', expectedEmail: $originalEmail);
            $this->fail('An old-address reset must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertSame(0, $user->fresh()->auth_version);
    }
}
