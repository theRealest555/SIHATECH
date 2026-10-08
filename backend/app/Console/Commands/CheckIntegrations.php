<?php

namespace App\Console\Commands;

use App\Services\SocialProviders;
use Illuminate\Console\Command;

class CheckIntegrations extends Command
{
    protected $signature = 'integrations:check {--mode=test : Stripe mode: test or live} {--json : Emit configuration results without credentials}';

    protected $description = 'Read-only provider configuration check; does not contact providers or prove delivery';

    public function handle(SocialProviders $providers): int
    {
        $mode = $this->option('mode');
        if (! in_array($mode, ['test', 'live'], true)) {
            $this->error('Mode must be test or live.');

            return self::INVALID;
        }

        $usable = fn ($value) => is_string($value) && trim($value) !== ''
            && ! preg_match('/dummy|placeholder|example|changeme|your[_-]/i', $value);
        $checks = [];
        foreach (['key' => 'pk_', 'secret' => 'sk_', 'webhook_secret' => 'whsec_'] as $field => $prefix) {
            $value = config('services.stripe.'.$field);
            $expected = $field === 'webhook_secret' ? $prefix : $prefix.$mode.'_';
            $checks['stripe_'.$field] = $usable($value) && str_starts_with($value, $expected) ? 'configured' : 'missing_or_invalid';
        }
        foreach (['google', 'facebook'] as $provider) {
            $checks[$provider] = ! config('services.'.$provider.'.enabled', true) ? 'disabled'
                : ($providers->available($provider)
                    && $usable(config('services.'.$provider.'.client_id'))
                    && $usable(config('services.'.$provider.'.client_secret'))
                    && rtrim(config('services.'.$provider.'.redirect'), '/') === rtrim(config('app.url'), '/').'/api/auth/social/'.$provider.'/callback'
                    ? 'configured' : 'missing_or_invalid');
        }
        $mailer = config('mail.default');
        $transport = config('mail.mailers.'.$mailer.'.transport');
        $smtpUrl = config('mail.mailers.'.$mailer.'.url');
        $smtpParts = is_string($smtpUrl) ? parse_url($smtpUrl) : false;
        $remoteHost = fn ($host) => $usable($host) && ! in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '[::1]'], true);
        $smtpUrlConfigured = $usable($smtpUrl) && is_array($smtpParts)
            && in_array($smtpParts['scheme'] ?? '', ['smtp', 'smtps'], true)
            && $remoteHost($smtpParts['host'] ?? null)
            && $usable($smtpParts['user'] ?? null) && $usable($smtpParts['pass'] ?? null);
        $checks['mail'] = match ($transport) {
            'smtp' => ($smtpUrl ? $smtpUrlConfigured : ($remoteHost(config('mail.mailers.'.$mailer.'.host'))
                    && $usable(config('mail.mailers.'.$mailer.'.username'))
                    && $usable(config('mail.mailers.'.$mailer.'.password')))) ? 'configured' : 'missing_or_invalid',
            'postmark' => $usable(config('services.postmark.token')) ? 'configured' : 'missing_or_invalid',
            'ses' => $usable(config('services.ses.key')) && $usable(config('services.ses.secret')) ? 'configured' : 'missing_or_invalid',
            default => 'requires_manual_check',
        };
        $checks['mail_sender'] = filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
            && ! preg_match('/\.(test|invalid|localhost)$|@example\./i', config('mail.from.address')) ? 'configured' : 'missing_or_invalid';

        if ($this->option('json')) {
            $this->line(json_encode(['mode' => $mode, 'checks' => $checks, 'scope' => 'configuration_only'], JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Integration', 'Configuration'], collect($checks)->map(fn ($status, $name) => [$name, $status])->values()->all());
            $this->info('No provider requests were sent. Verify real delivery and callbacks in staging.');
        }

        return count(array_diff($checks, ['configured', 'disabled'])) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
