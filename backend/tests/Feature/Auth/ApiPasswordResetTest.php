<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash; // Laravel's default ResetPassword notification
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password; // Corrected Event name
use Tests\TestCase;

class ApiPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_link_can_be_requested_via_api(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->postJson('/api/forgot-password', ['email' => $user->email]); // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]

        $response->assertStatus(200)
            ->assertJson(['message' => 'If an account matches this email, a password reset link will be sent. Check your inbox and spam folder.']);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_link_request_is_generic_for_non_existent_email_via_api(): void
    {
        $response = $this->postJson('/api/forgot-password', ['email' => 'nonexistent@example.com']); // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]

        $response->assertOk()->assertJson(['message' => 'If an account matches this email, a password reset link will be sent. Check your inbox and spam folder.']);
    }

    public function test_password_can_be_reset_with_valid_token_via_api(): void
    {
        Notification::fake();
        Event::fake(PasswordResetEvent::class);

        $user = User::factory()->create();
        $token = Password::createToken($user);

        $newPassword = 'newSecurePassword123';
        $response = $this->postJson('/api/reset-password', [ // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => __('passwords.reset')]);

        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        Event::assertDispatched(PasswordResetEvent::class, function ($event) use ($user) {
            return $event->user->is($user);
        });
    }

    public function test_password_reset_fails_with_invalid_token_via_api(): void
    {
        $user = User::factory()->create();
        $newPassword = 'newSecurePassword123';

        $response = $this->postJson('/api/reset-password', [ // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertStatus(422) // Validation error for token
            ->assertJsonValidationErrors(['email']); // Laravel's default error message for invalid token is on 'email'
    }

    public function test_password_reset_fails_with_mismatched_passwords_via_api(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $response = $this->postJson('/api/reset-password', [ // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]
            'token' => $token,
            'email' => $user->email,
            'password' => 'newPassword123',
            'password_confirmation' => 'differentPassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_reset_requires_all_fields_via_api(): void
    {
        $response = $this->postJson('/api/reset-password', []); // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'email', 'password']);
    }
}
