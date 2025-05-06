<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DoctorVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public bool $approved, public ?string $reason = null)
    {
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        if ($this->approved) {
            return (new MailMessage)
                ->subject('Votre compte médecin a été approuvé')
                ->line('Votre compte médecin a été approuvé avec succès.')
                ->action('Accéder à votre compte', url('/login'));
        }

        return (new MailMessage)
            ->subject('Votre compte médecin a été rejeté')
            ->line('Malheureusement, votre compte médecin n\'a pas été approuvé.')
            ->line('Raison: ' . $this->reason)
            ->line('Vous pouvez soumettre à nouveau votre demande avec les documents corrects.');
    }

    public function toArray($notifiable)
    {
        return [
            'approved' => $this->approved,
            'reason' => $this->reason,
            'message' => $this->approved 
                ? 'Votre compte médecin a été approuvé' 
                : 'Votre compte a été rejeté: ' . $this->reason,
        ];
    }
}