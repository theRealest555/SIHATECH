<?php

namespace App\Notifications;

use App\Models\rendezvous;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage; // Ensure this is correctly imported
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class AppointmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $rendezvous;
    protected $timeUntilAppointment;

    /**
     * Create a new notification instance.
     *
     * @param rendezvous $rendezvous
     * @param string $timeUntilAppointment '24h', '1h', etc.
     */
    public function __construct(rendezvous $rendezvous, string $timeUntilAppointment)
    {
        $this->rendezvous = $rendezvous;
        $this->timeUntilAppointment = $timeUntilAppointment;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        // Use selected channels based on user preferences or default to both
        $channels = ['mail', 'database'];
        
        // Check if the user has a phone number for SMS
        if ($notifiable->telephone) {
            $channels[] = 'vonage'; // For SMS via Vonage/Nexmo
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $doctor = $this->rendezvous->doctor->user;
        $appointmentTime = Carbon::parse($this->rendezvous->date_heure)->format('d/m/Y à H:i');
        
        return (new MailMessage)
            ->subject('Rappel de rendez-vous médical')
            ->greeting('Bonjour ' . $notifiable->prenom . ' ' . $notifiable->nom)
            ->line('Nous vous rappelons votre rendez-vous médical chez:')
            ->line('Dr. ' . $doctor->prenom . ' ' . $doctor->nom)
            ->line('Date et heure: ' . $appointmentTime)
            ->line('Adresse: ' . $doctor->adresse)
            ->action('Voir les détails', url('/patient/rendezvous/' . $this->rendezvous->id))
            ->line('Si vous ne pouvez pas vous présenter à ce rendez-vous, veuillez l\'annuler ou le reprogrammer dès que possible.');
    }

    /**
     * Get the SMS representation of the notification.
    public function toVonage($notifiable): VonageMessage // Or NexmoMessage if VonageMessage is unavailable
    public function toVonage($notifiable): VonageMessage
    {
        $doctor = $this->rendezvous->doctor->user;
        $appointmentTime = Carbon::parse($this->rendezvous->date_heure)->format('d/m/Y à H:i');
        
        return (new VonageMessage)
            ->content("Rappel: Rendez-vous médical le {$appointmentTime} avec Dr. {$doctor->prenom} {$doctor->nom}. Pour annuler ou reprogrammer, connectez-vous sur notre site.")
            ->from('DOCRDV'); // Custom sender name, must be registered with Vonage
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        $doctor = $this->rendezvous->doctor->user;
        $appointmentTime = Carbon::parse($this->rendezvous->date_heure);
        
        return [
            'rendezvous_id' => $this->rendezvous->id,
            'doctor_id' => $this->rendezvous->doctor_id,
            'doctor_name' => $doctor->prenom . ' ' . $doctor->nom,
            'appointment_time' => $this->rendezvous->date_heure,
            'time_until' => $this->timeUntilAppointment,
            'message' => "Rappel de votre rendez-vous le " . $appointmentTime->format('d/m/Y à H:i')
        ];
    }
}