<?php

namespace App\Notifications;

use App\Models\Rendezvous;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $rendezvous;

    protected $timeUntilAppointment;

    public function __construct(Rendezvous $rendezvous, string $timeUntilAppointment)
    {
        $this->rendezvous = $rendezvous;
        $this->timeUntilAppointment = $timeUntilAppointment;
    }

    public function via($notifiable): array
    {
        $channels = ['mail', 'database'];

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $doctor = $this->rendezvous->doctor->user;
        $appointmentTime = Carbon::parse($this->rendezvous->date_heure)->timezone(config('app.timezone'))->format('d/m/Y à H:i T');

        return (new MailMessage)
            ->subject('Rappel de rendez-vous médical')
            ->greeting('Bonjour '.$notifiable->prenom.' '.$notifiable->nom)
            ->line('Nous vous rappelons votre rendez-vous médical chez:')
            ->line('Dr. '.$doctor->prenom.' '.$doctor->nom)
            ->line('Date et heure: '.$appointmentTime)
            ->line('Adresse: '.$doctor->adresse)
            ->action('Voir les détails', rtrim(config('app.frontend_url'), '/').'/patient/appointments')
            ->line('Si vous ne pouvez pas vous présenter à ce rendez-vous, veuillez l\'annuler ou le reprogrammer dès que possible.');
    }

    public function toArray($notifiable): array
    {
        $doctor = $this->rendezvous->doctor->user;
        $appointmentTime = Carbon::parse($this->rendezvous->date_heure);

        return [
            'rendezvous_id' => $this->rendezvous->id,
            'doctor_id' => $this->rendezvous->doctor_id,
            'doctor_name' => $doctor->prenom.' '.$doctor->nom,
            'appointment_time' => $this->rendezvous->date_heure,
            'time_until' => $this->timeUntilAppointment,
            'message' => 'Rappel de votre rendez-vous le '.$appointmentTime->format('d/m/Y à H:i'),
        ];
    }
}
