<?php

namespace Tests;

use App\Models\User;
use App\Services\AccountStatusRevision;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function statusDecision(User $user, array $payload): array
    {
        $fresh = $user->fresh();

        $removesAccess = (isset($payload['status']) && $payload['status'] !== 'actif')
            || (isset($payload['admin_status']) && (int) $payload['admin_status'] === 0);

        return $payload + ['expected_status_revision' => app(AccountStatusRevision::class)->token($fresh, $fresh->admin)]
            + ($removesAccess ? ['reason' => 'Fixture administrative access review'] : []);
    }
}
