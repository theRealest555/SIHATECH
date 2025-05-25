<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Notifications\PaymentSuccessNotification;
use App\Services\StripePaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payment;

    /**
     * Create a new job instance.
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Execute the job.
     */
    public function handle(StripePaymentService $stripeService): void
    {
        try {
            Log::info('Processing payment', [
                'payment_id' => $this->payment->id,
                'amount' => $this->payment->amount,
                'user_id' => $this->payment->user_id,
            ]);

            // If payment is already processed, skip
            if ($this->payment->status !== 'pending') {
                Log::info('Payment already processed', [
                    'payment_id' => $this->payment->id,
                    'status' => $this->payment->status,
                ]);
                return;
            }

            // Here you would typically:
            // 1. Check payment status with Stripe
            // 2. Update payment status accordingly
            // 3. Send notifications
            // 4. Update subscription status if applicable

            // For now, we'll assume the payment is successful
            // In reality, you'd check with Stripe API

            $this->payment->update(['status' => 'completed']);

            // Send success notification
            if ($this->payment->user) {
                $this->payment->user->notify(new PaymentSuccessNotification($this->payment));
            }

            // If this is a subscription payment, ensure subscription is active
            if ($this->payment->user_subscription_id && $this->payment->userSubscription) {
                $this->payment->userSubscription->update(['status' => 'active']);
            }

            Log::info('Payment processed successfully', [
                'payment_id' => $this->payment->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'payment_id' => $this->payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update payment status to failed
            $this->payment->update([
                'status' => 'failed',
                'payment_data' => array_merge($this->payment->payment_data ?? [], [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString(),
                ])
            ]);

            // Optionally, you could retry the job
            if ($this->attempts() < 3) {
                $this->release(60); // Retry after 60 seconds
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Payment job failed after all retries', [
            'payment_id' => $this->payment->id,
            'error' => $exception->getMessage(),
        ]);

        // Update payment status to failed
        $this->payment->update([
            'status' => 'failed',
            'payment_data' => array_merge($this->payment->payment_data ?? [], [
                'final_error' => $exception->getMessage(),
                'permanently_failed_at' => now()->toISOString(),
            ])
        ]);
    }
}
