<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum; // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/app/Notifications/VerifyEmailNotification.php]
use Tests\TestCase;

class ApiEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_link_uses_web_session_without_spa_origin(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $this->assertContains('web', app('router')->getRoutes()->getByName('verification.verify')->gatherMiddleware());
        $this->actingAs($user, 'web')->get($url)->assertRedirect(config('verification.redirect.success'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_can_be_verified_via_api_route(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        Event::fake(); // Fake the Verified event

        // Generate the verification URL as Laravel does
        // The VerifyEmailController uses the default URL generation if not overridden
        // The EmailVerificationServiceProvider customizes the URL generation for VerifyEmailNotification
        // For testing the controller's __invoke method, we need the URL it expects.
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify', // Route name from routes/api.php [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Simulate user being logged in when hitting the verification URL
        Sanctum::actingAs($user);
        $response = $this->get($verificationUrl);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);

        // Check the redirect URL from your config/verification.php [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/config/verification.php]
        $expectedRedirect = config('verification.redirect.success', env('FRONTEND_URL', 'http://localhost:3000').'/dashboard?verified=1');
        $response->assertRedirect($expectedRedirect);
    }

    public function test_email_verification_error_route_redirects_correctly(): void
    {
        // Act: Make a GET request to the verification error route
        // This route is defined in routes/api.php
        $response = $this->getJson('/api/email/verify/error');

        // Assert: Check that the response is a redirect to the URL defined in config/verification.php
        // The config('verification.redirect.error') should point to your frontend's error page for this.
        // Example: env('FRONTEND_URL', 'http://localhost:3000') . '/email/verify/error'
        $expectedErrorRedirectUrl = config('verification.redirect.error');

        $response->assertRedirect($expectedErrorRedirectUrl);
    }

    public function test_email_is_not_verified_with_invalid_hash_via_api(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify', // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        Sanctum::actingAs($user);
        $response = $this->get($verificationUrl);

        // Laravel's default behavior for invalid signature is often a 403 or similar.
        // Check your specific handler or if it falls back to a generic error.
        // If your EmailVerificationRequest handles this, it might redirect or return a specific status.
        // For a signed URL mismatch, it's typically a 403.
        $response->assertStatus(403);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_already_verified_user_is_redirected_when_hitting_verify_route(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]); // Already verified

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify', // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        Sanctum::actingAs($user);
        $response = $this->get($verificationUrl);

        $expectedRedirect = config('verification.redirect.already_verified', env('FRONTEND_URL', 'http://localhost:3000').'/dashboard?verified=1');
        $response->assertRedirect($expectedRedirect);
    }

    public function test_unverified_user_can_request_new_verification_email_via_api(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => null]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/email/verification-notification'); // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]

        $response->assertStatus(200)
            ->assertJson(['status' => 'verification-link-sent']);

        Notification::assertSentTo($user, VerifyEmailNotification::class); // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/app/Notifications/VerifyEmailNotification.php]
    }

    public function test_verified_user_requesting_new_verification_email_gets_json_via_api(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/email/verification-notification'); // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]

        $response->assertOk()->assertJson(['status' => 'already-verified']);
    }

    public function test_unauthenticated_user_cannot_request_verification_email_via_api(): void
    {
        $response = $this->postJson('/api/email/verification-notification'); // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]
        $response->assertStatus(401);
    }

    public function test_user_can_check_email_verification_status_via_api(): void
    {
        $verifiedUser = User::factory()->create(['email_verified_at' => now()]);
        $unverifiedUser = User::factory()->create(['email_verified_at' => null]);

        Sanctum::actingAs($verifiedUser);
        $responseVerified = $this->getJson('/api/email/verify/check'); // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]
        $responseVerified->assertStatus(200)->assertJson(['verified' => true]);

        Sanctum::actingAs($unverifiedUser);
        $responseUnverified = $this->getJson('/api/email/verify/check'); // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]
        $responseUnverified->assertStatus(200)->assertJson(['verified' => false]);
    }
}
