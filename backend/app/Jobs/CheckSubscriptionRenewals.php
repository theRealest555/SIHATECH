<?php

namespace App\Jobs;

use App\Services\SubscriptionPeriodAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

// Keep the class for already queued legacy jobs. Period end alone cannot prove
// provider cancellation or justify a new purchase request to the customer.
class CheckSubscriptionRenewals implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $counts = app(SubscriptionPeriodAudit::class)->counts();
        if (array_sum($counts) > 0) {
            Log::warning('Subscription period audit requires provider reconciliation', $counts);
        } else {
            Log::info('Subscription period audit found no local anomalies', $counts);
        }
    }
}
