<?php

namespace App\Notifications;

use App\Models\UserSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Retained for explicit use only; the legacy audit job does not send notices.
class SubscriptionRenewalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected UserSubscription $subscription, protected int $daysUntilExpiry) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Période d\'abonnement - SIHATECH')
            ->greeting('Bonjour '.$notifiable->prenom.' '.$notifiable->nom)
            ->line('La période enregistrée pour votre abonnement '.($this->subscription->subscriptionPlan->name ?? 'SIHATECH').' se termine le '.$this->subscription->ends_at->format('d/m/Y H:i T').'.')
            ->line('Le renouvellement dépend du statut de paiement confirmé par le prestataire. Consultez votre abonnement pour vérifier sa situation.')
            ->action('Consulter mon abonnement', rtrim(config('app.frontend_url'), '/').'/my-subscription');
    }

    public function toArray($notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->subscriptionPlan->name ?? 'SIHATECH',
            'period_end' => $this->subscription->ends_at->toIso8601String(),
            'days_until_period_end' => $this->daysUntilExpiry,
            'message' => 'Consultez votre abonnement pour vérifier le statut de la période et du paiement.',
        ];
    }
}
