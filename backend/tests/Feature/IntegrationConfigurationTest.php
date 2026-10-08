<?php

namespace Tests\Feature;

use Tests\TestCase;

class IntegrationConfigurationTest extends TestCase
{
    public function test_invalid_or_local_smtp_urls_do_not_fall_back_to_valid_host_settings(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.sihatech.test', 'mail.mailers.smtp.username' => 'staging-user', 'mail.mailers.smtp.password' => 'staging-password']);
        foreach (['not-a-mail-url', 'smtp://staging-user:staging-password@127.0.0.1:8265'] as $url) {
            config(['mail.mailers.smtp.url' => $url]);
            $this->artisan('integrations:check --json')->expectsOutputToContain('"mail":"missing_or_invalid"')->assertFailed();
        }
    }

    public function test_dummy_credentials_and_local_mail_cannot_pass_a_release_check(): void
    {
        config(['services.stripe.key' => 'pk_test_dummy', 'services.stripe.secret' => 'sk_test_dummy', 'services.stripe.webhook_secret' => 'whsec_test_dummy', 'services.google.enabled' => false, 'services.facebook.enabled' => false, 'mail.default' => 'array']);
        $this->artisan('integrations:check --json')->expectsOutputToContain('missing_or_invalid')->assertFailed();
    }

    public function test_mode_mismatches_and_incorrect_callback_hosts_fail(): void
    {
        config(['services.stripe.key' => 'pk_live_123456', 'services.stripe.secret' => 'sk_live_123456', 'services.stripe.webhook_secret' => 'whsec_123456', 'services.google.enabled' => true, 'services.google.client_id' => '123456', 'services.google.client_secret' => '123456', 'services.google.redirect' => 'https://other.test/api/auth/social/google/callback', 'app.url' => 'https://api.sihatech.test']);
        $this->artisan('integrations:check --mode=test --json')->expectsOutputToContain('"google":"missing_or_invalid"')->assertFailed();
        $this->artisan('integrations:check --mode=unknown')->assertExitCode(2);
    }

    public function test_valid_configuration_is_reported_without_exposing_secrets(): void
    {
        config(['services.stripe.key' => 'pk_test_123456', 'services.stripe.secret' => 'sk_test_123456', 'services.stripe.webhook_secret' => 'whsec_123456', 'services.google.enabled' => false, 'services.facebook.enabled' => false, 'mail.default' => 'smtp', 'mail.mailers.smtp.url' => null, 'mail.mailers.smtp.host' => 'smtp.sihatech.test', 'mail.mailers.smtp.username' => 'staging-user', 'mail.mailers.smtp.password' => 'staging-password', 'mail.from.address' => 'notifications@sihatech.ma']);
        $this->artisan('integrations:check --json')->expectsOutput(json_encode(['mode' => 'test', 'checks' => ['stripe_key' => 'configured', 'stripe_secret' => 'configured', 'stripe_webhook_secret' => 'configured', 'google' => 'disabled', 'facebook' => 'disabled', 'mail' => 'configured', 'mail_sender' => 'configured'], 'scope' => 'configuration_only']))->assertSuccessful();
    }
}
