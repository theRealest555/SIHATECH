<?php

namespace App\Console\Commands;

use App\Services\SubscriptionPeriodAudit;
use Illuminate\Console\Command;

class AuditSubscriptionPeriods extends Command
{
    protected $signature = 'subscriptions:audit-periods {--json : Output aggregate counts as JSON}';

    protected $description = 'Read-only local subscription anomaly audit; does not contact Stripe or alter access';

    public function handle(SubscriptionPeriodAudit $audit): int
    {
        $counts = $audit->counts();
        if ($this->option('json')) {
            $this->line(json_encode($counts, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Count'], collect($counts)->map(fn ($count, $check) => [$check, $count])->values()->all());
            $this->info('Review anomalies against provider records; counts can overlap. No records were changed.');
        }

        return array_sum($counts) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
