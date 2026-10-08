<?php

namespace App\Jobs;

use App\Models\Rendezvous;
use App\Notifications\AppointmentReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class DeliverAppointmentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $reminderId) {}

    public function handle(): void
    {
        if (! config('operations.appointment_reminders_enabled')) {
            return;
        }
        $delivery = DB::transaction(function () {
            // Lock the appointment before its ledger, consistently with scan writes.
            $candidate = DB::table('appointment_reminders')->find($this->reminderId);
            if (! $candidate) {
                return null;
            }
            $appointment = Rendezvous::with(['patient.user', 'doctor.user'])->whereKey($candidate->appointment_id)->lockForUpdate()->first();
            $reminder = DB::table('appointment_reminders')->where('id', $this->reminderId)->lockForUpdate()->first();
            if (! $reminder || $reminder->mail_status !== 'pending') {
                return null;
            }
            $patient = $appointment?->patient?->user;
            if (! $appointment || $appointment->statut !== 'confirmé' || $appointment->date_heure->format('Y-m-d H:i:s') !== $reminder->starts_at
                || $appointment->date_heure->lte(now()->addMinutes(30)) || $appointment->date_heure->gt(now()->addHours(24))
                || ! $patient || ! $patient->isActive() || ! $patient->hasVerifiedEmail() || ! $appointment->doctor?->user?->isActive()) {
                DB::table('appointment_reminders')->where('id', $this->reminderId)->update(['mail_status' => 'skipped', 'updated_at' => now()]);

                return null;
            }
            $notification = new AppointmentReminderNotification($appointment, 'within_24_hours');
            Notification::sendNow($patient, $notification, ['database']);
            DB::table('appointment_reminders')->where('id', $this->reminderId)->update(['mail_status' => 'sending', 'attempted_at' => now(), 'updated_at' => now()]);

            return [$patient, $notification];
        });
        if (! $delivery) {
            return;
        }
        try {
            // External mail happens after commit. An uncertain delivery is never
            // automatically attempted again; database notifications stay durable.
            Notification::sendNow($delivery[0], $delivery[1], ['mail']);
            DB::table('appointment_reminders')->where('id', $this->reminderId)->update(['mail_status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $exception) {
            DB::table('appointment_reminders')->where('id', $this->reminderId)->update(['mail_status' => 'failed', 'updated_at' => now()]);
            report($exception);
            throw $exception;
        }
    }
}
