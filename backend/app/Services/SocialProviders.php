<?php

namespace App\Services;

class SocialProviders
{
    public function available(string $provider): bool
    {
        if (! in_array($provider, ['google', 'facebook'], true) || ! config('services.'.$provider.'.enabled', true)) {
            return false;
        }
        foreach (['client_id', 'client_secret', 'redirect'] as $field) {
            $value = config('services.'.$provider.'.'.$field);
            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }
        $url = config('services.'.$provider.'.redirect');
        $parts = parse_url($url);

        return filter_var($url, FILTER_VALIDATE_URL) !== false && is_array($parts)
            && in_array($parts['scheme'] ?? '', app()->environment(['production', 'staging']) ? ['https'] : ['http', 'https'], true)
            && ($parts['path'] ?? '') === '/api/auth/social/'.$provider.'/callback'
            && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['query']) && ! isset($parts['fragment']);
    }

    public function publicOptions(): array
    {
        return [
            'google' => ['available' => $this->available('google'), 'existing_accounts_only' => false],
            'facebook' => ['available' => $this->available('facebook'), 'existing_accounts_only' => true],
        ];
    }
}
