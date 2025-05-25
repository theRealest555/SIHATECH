<?php

namespace App\Notifications;

use App\Models\Rendezvous;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentNoShowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $appointment;

    /**
     * Create a new notification instance.
     */
    public function __construct(Rendezvous $appointment)
    {
        $this->appointment = $appointment;
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
        $doctor = $this->appointment->doctor->user;
        $appointmentTime = $this->appointment->date_heure->format('d/m/Y à H:i');

        return (new MailMessage)
            ->subject('Rendez-vous manqué - SIHATECH')
            ->greeting('Bonjour ' . $notifiable->prenom . ' ' . $notifiable->nom)
            ->line('Nous avons constaté que vous n\'avez pas pu vous présenter à votre rendez-vous.')
            ->line('**Détails du rendez-vous manqué :**')
            ->line('- Médecin : Dr. ' . $doctor->prenom . ' ' . $doctor->nom)
            ->line('- Date et heure : ' . $appointmentTime)
            ->line('- Spécialité : ' . ($this->appointment->doctor->speciality->nom ?? 'N/A'))
            ->line('Si vous souhaitez reprogrammer ce rendez-vous, nous vous invitons à prendre contact avec le cabinet ou à utiliser notre plateforme.')
            ->action('Prendre un nouveau rendez-vous', url('/doctors/' . $this->appointment->doctor_id))
            ->line('Pour éviter les rendez-vous manqués à l\'avenir, nous vous recommandons d\'annuler au moins 24h à l\'avance si vous ne pouvez pas vous présenter.')
            ->line('Merci de votre compréhension.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        $doctor = $this->appointment->doctor->user;

        return [
            'appointment_id' => $this->appointment->id,
            'doctor_id' => $this->appointment->doctor_id,
            'doctor_name' => 'Dr. ' . $doctor->prenom . ' ' . $doctor->nom,
            'appointment_time' => $this->appointment->date_heure->format('Y-m-d H:i:s'),
            'type' => 'no_show',
            'message' => 'Vous avez manqué votre rendez-vous du ' . $this->appointment->date_heure->format('d/m/Y à H:i'),
        ];
    }
}
