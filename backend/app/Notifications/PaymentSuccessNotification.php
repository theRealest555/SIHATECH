<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $payment;

    /**
     * Create a new notification instance.
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
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
        $planName = $this->payment->userSubscription && $this->payment->userSubscription->subscriptionPlan
            ? $this->payment->userSubscription->subscriptionPlan->name
            : 'Service SIHATECH';

        return (new MailMessage)
            ->subject('Confirmation de paiement - SIHATECH')
            ->greeting('Bonjour ' . $notifiable->prenom . ' ' . $notifiable->nom)
            ->line('Nous confirmons la réception de votre paiement.')
            ->line('**Détails de la transaction :**')
            ->line('- Montant : ' . number_format($this->payment->amount, 2) . ' ' . $this->payment->currency)
            ->line('- Service : ' . $planName)
            ->line('- Numéro de transaction : ' . $this->payment->transaction_id)
            ->line('- Date : ' . $this->payment->created_at->format('d/m/Y à H:i'))
            ->action('Voir mon compte', url('/doctor/subscription'))
            ->line('Merci de votre confiance.')
            ->line('Si vous avez des questions concernant ce paiement, n\'hésitez pas à nous contacter.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'transaction_id' => $this->payment->transaction_id,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency,
            'plan_name' => $this->payment->userSubscription && $this->payment->userSubscription->subscriptionPlan
                ? $this->payment->userSubscription->subscriptionPlan->name
                : 'Service SIHATECH',
            'date' => $this->payment->created_at->format('Y-m-d H:i:s'),
            'message' => 'Paiement de ' . number_format($this->payment->amount, 2) . ' ' . $this->payment->currency . ' confirmé',
        ];
    }
}
