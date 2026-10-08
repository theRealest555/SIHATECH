<?php

namespace App\Console\Commands;

use App\Jobs\DeliverAppointmentReminder;
use App\Models\Rendezvous;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Queue one reminder per confirmed appointment time within the next 24 hours';

    public function handle(): int
    {
        if (! config('operations.appointment_reminders_enabled')) {
            $this->info('Appointment reminders are disabled.');

            return self::SUCCESS;
        }

        Rendezvous::where('statut', 'confirmé')->where('date_heure', '>', now()->addMinutes(30))
            ->where('date_heure', '<=', now()->addHours(24))->select('id')->chunkById(200, function ($appointments) {
                foreach ($appointments as $candidate) {
                    DB::transaction(function () use ($candidate) {
                        $appointment = Rendezvous::whereKey($candidate->id)->lockForUpdate()->first();
                        if (! $appointment || $appointment->statut !== 'confirmé' || $appointment->date_heure->lte(now()->addMinutes(30)) || $appointment->date_heure->gt(now()->addHours(24))) {
                            return;
                        }
                        $startsAt = $appointment->date_heure->format('Y-m-d H:i:s');
                        if (DB::table('appointment_reminders')->where('appointment_id', $appointment->id)->where('starts_at', $startsAt)->exists()) {
                            return;
                        }
                        DB::table('appointment_reminders')->insert([
                            'appointment_id' => $appointment->id,
                            'starts_at' => $startsAt,
                            'mail_status' => 'pending',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    });
                }
            });

        // Pending rows survive an enqueue failure and are picked up next time.
        DB::table('appointment_reminders')->where('mail_status', 'pending')->select('id')->chunkById(200, function ($reminders) {
            foreach ($reminders as $reminder) {
                DeliverAppointmentReminder::dispatch($reminder->id);
            }
        });

        return self::SUCCESS;
    }
}
