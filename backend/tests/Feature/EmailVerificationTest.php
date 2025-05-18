<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_receives_verification_email_after_registration()
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // Trigger the registered event
        event(new Registered($user));

        // Assert a notification was sent to the user
        Notification::assertSentTo(
            $user,
            \App\Notifications\VerifyEmailNotification::class
        );
    }

    public function test_user_can_verify_email()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // Create a signed URL using the same method as the notification
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Visit the verification URL
        $user = User::find($user->id); // Reload the user instance
        $response = $this->actingAs($user)->get($verificationUrl);

        // Should be redirected to the success URL
        $response->assertRedirect(config('verification.redirect.success'));

        // Assert the user is now verified
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verified_user_is_redirected_when_visiting_verification_url()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // Create a signed URL using the same method as the notification
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Visit the verification URL
        $user = User::find($user->id);
        $response = $this->actingAs($user)->get($verificationUrl);

        // Should be redirected to the already verified URL
        $response->assertRedirect(config('verification.redirect.already_verified'));
    }

    public function test_user_can_request_another_verification_email()
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);
        $user = User::find($user->id);
        $response = $this->actingAs($user)
            ->post('/api/email/verification-notification');

        $response->assertJson(['status' => 'verification-link-sent']);

        Notification::assertSentTo(
            $user,
            \App\Notifications\VerifyEmailNotification::class
        );
    }

    public function test_unverified_user_cannot_access_protected_routes()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'role' => 'patient'
        ]);

        // Create API token with role ability
        $token = $user->createToken('test-token', [$user->role])->plainTextToken;

        // Try to access a protected route
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/patient/profile');

        // Should get a 409 Conflict response
        $response->assertStatus(409);
        $response->assertJson(['message' => 'Your email address is not verified.']);
    }

    public function test_verified_user_can_access_protected_routes()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'role' => 'patient'
        ]);

        // Create API token with role ability
        $token = $user->createToken('test-token', [$user->role])->plainTextToken;

        // Access a protected route
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/patient/profile');

        // Should be allowed access
        $response->assertStatus(200);
    }
}