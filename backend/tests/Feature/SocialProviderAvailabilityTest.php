<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class SocialProviderAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function configure(string $provider): void
    {
        config(['services.'.$provider.'.enabled' => true, 'services.'.$provider.'.client_id' => 'private-client-fixture',
            'services.'.$provider.'.client_secret' => 'private-secret-fixture', 'services.'.$provider.'.redirect' => 'https://api.preview.test/api/auth/social/'.$provider.'/callback']);
    }

    public function test_public_flags_do_not_expose_credentials_or_callback_urls(): void
    {
        $this->configure('google');
        $this->configure('facebook');
        $this->getJson('/api/public/auth/providers')->assertOk()->assertExactJson(['data' => [
            'google' => ['available' => true, 'existing_accounts_only' => false],
            'facebook' => ['available' => true, 'existing_accounts_only' => true],
        ]])->assertDontSee('private-secret-fixture')->assertDontSee('api.preview.test')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_disabled_or_missing_configuration_blocks_redirect_and_callback_without_provider_calls(): void
    {
        $this->configure('google');
        config(['services.google.enabled' => false]);
        Socialite::shouldReceive('driver')->never();
        $this->get('/api/auth/social/google/redirect')->assertRedirect('http://localhost:3000/login?error=provider_unavailable');
        $this->get('/api/auth/social/google/callback')->assertRedirect('http://localhost:3000/login?error=provider_unavailable');
        $this->assertGuest('web');
        $this->getJson('/api/public/auth/providers')->assertJsonPath('data.google.available', false);
        config(['services.google.enabled' => true, 'services.google.client_secret' => '']);
        $this->get('/api/auth/social/google/redirect')->assertRedirect('http://localhost:3000/login?error=provider_unavailable');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_callback_shape_and_non_https_staging_urls_are_unavailable(): void
    {
        $this->configure('google');
        foreach (['https://api.preview.test/wrong', 'https://user@api.preview.test/api/auth/social/google/callback',
            'https://api.preview.test/api/auth/social/google/callback?secret=fixture', 'ftp://api.preview.test/api/auth/social/google/callback'] as $url) {
            config(['services.google.redirect' => $url]);
            $this->getJson('/api/public/auth/providers')->assertJsonPath('data.google.available', false);
        }
        config(['services.google.redirect' => 'http://localhost:8000/api/auth/social/google/callback']);
        $this->getJson('/api/public/auth/providers')->assertJsonPath('data.google.available', true);
        $this->app['env'] = 'staging';
        $this->getJson('/api/public/auth/providers')->assertJsonPath('data.google.available', false);
    }
}
