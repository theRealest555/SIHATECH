<?php

namespace App\Jobs;

use App\Models\UserSubscription;
use App\Notifications\SubscriptionRenewalNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckSubscriptionRenewals implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting subscription renewal check');

        // Get active subscriptions expiring in the next 7 days
        $expiringSubscriptions = UserSubscription::with(['user', 'subscriptionPlan'])
            ->where('status', 'active')
            ->whereBetween('ends_at', [now(), now()->addDays(7)])
            ->get();

        foreach ($expiringSubscriptions as $subscription) {
            $daysUntilExpiry = now()->diffInDays($subscription->ends_at, false);

            // Send notifications at specific intervals: 7 days, 3 days, 1 day, and on expiry day
            if (in_array($daysUntilExpiry, [7, 3, 1, 0])) {
                try {
                    $subscription->user->notify(new SubscriptionRenewalNotification($subscription, $daysUntilExpiry));

                    Log::info('Subscription renewal notification sent', [
                        'user_id' => $subscription->user_id,
                        'subscription_id' => $subscription->id,
                        'days_until_expiry' => $daysUntilExpiry,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send subscription renewal notification', [
                        'user_id' => $subscription->user_id,
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Check for expired subscriptions
        $expiredSubscriptions = UserSubscription::with(['user'])
            ->where('status', 'active')
            ->where('ends_at', '<', now())
            ->get();

        foreach ($expiredSubscriptions as $subscription) {
            try {
                $subscription->update(['status' => 'expired']);

                Log::info('Subscription marked as expired', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to mark subscription as expired', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Subscription renewal check completed', [
            'expiring_count' => $expiringSubscriptions->count(),
            'expired_count' => $expiredSubscriptions->count(),
        ]);
    }
}
