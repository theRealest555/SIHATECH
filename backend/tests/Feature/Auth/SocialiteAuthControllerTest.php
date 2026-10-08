<?php

namespace Tests\Feature\Auth;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema; // Alias to avoid conflict
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

//

class SocialiteAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['google', 'facebook'] as $provider) {
            config(['services.'.$provider.'.enabled' => true, 'services.'.$provider.'.client_id' => 'fixture-client',
                'services.'.$provider.'.client_secret' => 'fixture-secret', 'services.'.$provider.'.redirect' => 'https://api.preview.test/api/auth/social/'.$provider.'/callback']);
        }
    }

    public function test_cancelled_provider_flow_leaves_the_user_unauthenticated(): void
    {
        Socialite::shouldReceive('driver')->never();
        $this->get('/api/auth/social/google/callback?error=access_denied')->assertRedirect('http://localhost:3000/login?error=cancelled');
        $this->assertGuest('web');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_state_has_a_specific_recovery_code(): void
    {
        Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);
        $this->get('/api/auth/social/google/callback')->assertRedirect('http://localhost:3000/login?error=session_expired');
        $this->assertGuest('web');
    }

    public function test_missing_provider_id_cannot_match_a_legacy_null_mapping(): void
    {
        $user = User::factory()->create(['provider' => 'google', 'provider_id' => null]);
        $identity = new SocialiteUser;
        $identity->id = null;
        $identity->email = $user->email;
        $identity->user = ['email_verified' => true];
        Socialite::shouldReceive('driver->user')->andReturn($identity);
        $this->get('/api/auth/social/google/callback')->assertRedirect('http://localhost:3000/login?error=authentication_failed');
        $this->assertGuest('web');
        $this->assertNull($user->fresh()->provider_id);
    }

    public function test_ambiguous_provider_mappings_do_not_choose_an_account(): void
    {
        // Exercise legacy data before the new uniqueness constraint can be applied.
        Schema::table('users', fn ($table) => $table->dropUnique('users_provider_identity_unique'));
        User::factory()->count(2)->create(['provider' => 'google', 'provider_id' => 'duplicate-fixture']);
        $identity = new SocialiteUser;
        $identity->id = 'duplicate-fixture';
        Socialite::shouldReceive('driver->user')->andReturn($identity);
        $this->get('/api/auth/social/google/callback')->assertRedirect('http://localhost:3000/login?error=authentication_failed');
        $this->assertGuest('web');
    }

    public function test_unverified_provider_email_cannot_create_an_account(): void
    {
        $identity = new SocialiteUser;
        $identity->id = 'unverified';
        $identity->email = 'unverified@example.com';
        $identity->user = ['email_verified' => false];
        Socialite::shouldReceive('driver->user')->andReturn($identity);

        $this->get('/api/auth/social/google/callback')
            ->assertRedirect('http://localhost:3000/login?error=verified_email_required');
        $this->assertDatabaseMissing('users', ['email' => $identity->email]);
        $this->assertGuest('web');
    }

    public function test_matching_email_does_not_automatically_link_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);
        $identity = new SocialiteUser;
        $identity->id = 'unlinked-provider';
        $identity->email = $user->email;
        $identity->user = ['email_verified' => true];
        Socialite::shouldReceive('driver->user')->andReturn($identity);

        $this->get('/api/auth/social/google/callback')
            ->assertRedirect('http://localhost:3000/login?error=account_link_required');
        $this->assertNull($user->fresh()->provider_id);
        $this->assertGuest('web');
    }

    public function test_social_auth_redirects_to_provider()
    {
        Socialite::shouldReceive('driver->redirect')->andReturn(redirect('http://fake-google-auth.com'));

        $response = $this->get('/api/auth/social/google/redirect'); //
        $response->assertRedirect('http://fake-google-auth.com');
    }

    public function test_social_auth_redirect_fails_for_unsupported_provider()
    {
        $response = $this->get('/api/auth/social/unsupported/redirect'); //
        $response->assertRedirect('http://localhost:3000/login?error=unsupported_provider');
    }

    public function test_social_auth_callback_creates_new_user_and_redirects_with_session()
    {
        $socialUser = new SocialiteUser;
        $socialUser->id = '12345';
        $socialUser->user = ['email_verified' => true];
        $socialUser->name = 'Social User';
        $socialUser->email = 'social@example.com';
        $socialUser->avatar = 'http://example.com/avatar.jpg';

        Socialite::shouldReceive('driver->user')->andReturn($socialUser);

        $response = $this->get('/api/auth/social/google/callback'); //

        $this->assertDatabaseHas('users', [
            'email' => 'social@example.com',
            'provider' => 'google',
            'provider_id' => '12345',
        ]);
        $user = User::where('email', 'social@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->patient); //
        $this->assertTrue(Hash::check(Str::random(16), $user->password) == false); // Check a random password doesn't match

        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');
        $this->assertStringContainsString('http://localhost:3000/auth/google/callback', $redirectUrl);
        $this->assertStringNotContainsString('token=', $redirectUrl);
        $this->assertStringNotContainsString('user=', $redirectUrl);
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_social_auth_callback_logs_in_existing_user()
    {
        $existingUser = User::factory()->create([
            'email' => 'existing.social@example.com',
            'provider' => 'facebook',
            'provider_id' => '67890',
        ]);
        Patient::factory()->create(['user_id' => $existingUser->id]); //

        $socialUser = new SocialiteUser;
        $socialUser->id = '67890';
        $socialUser->name = $existingUser->prenom.' '.$existingUser->nom;
        $socialUser->email = $existingUser->email;
        $socialUser->avatar = 'http://example.com/avatar.jpg';

        Socialite::shouldReceive('driver->user')->andReturn($socialUser);

        $response = $this->get('/api/auth/social/facebook/callback'); //

        $this->assertDatabaseHas('users', [
            'email' => $existingUser->email,
            'provider' => 'facebook',
            'provider_id' => '67890',
        ]);

        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');
        $this->assertStringNotContainsString('token=', $redirectUrl);
    }

    public function test_social_auth_callback_handles_socialite_exception()
    {
        Socialite::shouldReceive('driver->user')->andThrow(new \Exception('Socialite error'));

        $response = $this->get('/api/auth/social/google/callback'); //

        $response->assertRedirectContains('http://localhost:3000/login?error=authentication_failed');
    }
}
