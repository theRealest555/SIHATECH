<?php

namespace App\Jobs;

use App\Models\Rendezvous;
use App\Notifications\AppointmentNoShowNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MarkNoShowAppointments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting no-show appointments check');

        // Get appointments that are:
        // - Status is 'confirmé' or 'en_attente'
        // - Date is more than 30 minutes in the past
        // - Not already marked as completed or no-show
        $noShowAppointments = Rendezvous::with(['patient', 'doctor.user'])
            ->whereIn('statut', ['confirmé', 'en_attente'])
            ->where('date_heure', '<', now()->subMinutes(30))
            ->get();

        foreach ($noShowAppointments as $appointment) {
            try {
                // Mark appointment as no-show
                $appointment->update(['statut' => 'no_show']);

                // Send notification to patient
                if ($appointment->patient) {
                    $appointment->patient->notify(new AppointmentNoShowNotification($appointment));
                }

                // Update doctor's statistics
                if ($appointment->doctor) {
                    $appointment->doctor->updateAverageRating(); // This will trigger recalculation
                }

                Log::info('Appointment marked as no-show', [
                    'appointment_id' => $appointment->id,
                    'patient_id' => $appointment->patient_id,
                    'doctor_id' => $appointment->doctor_id,
                    'appointment_time' => $appointment->date_heure,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to mark appointment as no-show', [
                    'appointment_id' => $appointment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('No-show appointments check completed', [
            'marked_count' => $noShowAppointments->count(),
        ]);
    }
}
