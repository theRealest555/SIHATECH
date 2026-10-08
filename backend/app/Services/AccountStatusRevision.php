<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\User;

class AccountStatusRevision
{
    public function token(User $user, ?Admin $admin = null): string
    {
        return hash_hmac('sha256', json_encode([
            'id' => $user->id,
            'role' => $user->role,
            'status' => $user->status,
            'auth_version' => $user->auth_version,
            'admin_status' => $admin ? (int) $admin->admin_status : null,
        ], JSON_THROW_ON_ERROR), config('app.key'));
    }

    public function assertCurrent(string $expected, User $user, ?Admin $admin = null): void
    {
        abort_unless(hash_equals($this->token($user, $admin), $expected), 409,
            'Account access changed in another session. Reload users and review the latest status before deciding again.');
    }
}
