<?php

namespace App\Notifications;

use App\Models\UserSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRenewalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $subscription;
    protected $daysUntilExpiry;

    /**
     * Create a new notification instance.
     */
    public function __construct(UserSubscription $subscription, int $daysUntilExpiry)
    {
        $this->subscription = $subscription;
        $this->daysUntilExpiry = $daysUntilExpiry;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $planName = $this->subscription->subscriptionPlan->name ?? 'Unknown';
        $expiryDate = $this->subscription->ends_at->format('d/m/Y');
        $amount = $this->subscription->subscriptionPlan->price ?? 0;

        $mailMessage = (new MailMessage)
            ->subject('Rappel de renouvellement d\'abonnement - SIHATECH')
            ->greeting('Bonjour ' . $notifiable->prenom . ' ' . $notifiable->nom);

        if ($this->daysUntilExpiry === 0) {
            $mailMessage->line('Votre abonnement ' . $planName . ' expire aujourd\'hui.')
                ->line('Pour continuer à bénéficier de nos services sans interruption, veuillez renouveler votre abonnement.');
        } elseif ($this->daysUntilExpiry === 1) {
            $mailMessage->line('Votre abonnement ' . $planName . ' expire demain.')
                ->line('N\'oubliez pas de renouveler pour continuer à accéder à toutes les fonctionnalités.');
        } else {
            $mailMessage->line('Votre abonnement ' . $planName . ' expire dans ' . $this->daysUntilExpiry . ' jours.')
                ->line('Date d\'expiration : ' . $expiryDate);
        }

        return $mailMessage
            ->line('Montant du renouvellement : ' . number_format($amount, 2) . ' MAD')
            ->action('Renouveler maintenant', url('/doctor/subscription'))
            ->line('Si vous avez des questions, n\'hésitez pas à nous contacter.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->subscriptionPlan->name ?? 'Unknown',
            'expiry_date' => $this->subscription->ends_at->format('Y-m-d'),
            'days_until_expiry' => $this->daysUntilExpiry,
            'amount' => $this->subscription->subscriptionPlan->price ?? 0,
            'message' => $this->daysUntilExpiry === 0
                ? 'Votre abonnement expire aujourd\'hui'
                : 'Votre abonnement expire dans ' . $this->daysUntilExpiry . ' jours',
        ];
    }
}
