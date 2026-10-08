<?php

namespace App\Services;

use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;

class SubscriptionPeriodAudit
{
    public function counts(): array
    {
        $at = now();

        return DB::transaction(fn () => [
            'ended_active_periods' => UserSubscription::where('status', 'active')->where('ends_at', '<=', $at)->count(),
            'invalid_periods' => UserSubscription::whereColumn('ends_at', '<=', 'starts_at')->count(),
            'active_without_completed_payment' => UserSubscription::where('status', 'active')
                ->whereDoesntHave('payments', fn ($query) => $query->where('status', 'completed'))->count(),
        ]);
    }
}
