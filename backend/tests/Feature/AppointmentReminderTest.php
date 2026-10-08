<?php

namespace Tests\Feature;

use App\Jobs\DeliverAppointmentReminder;
use App\Models\Rendezvous;
use App\Notifications\AppointmentReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AppointmentReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        config(['operations.appointment_reminders_enabled' => true]);
    }

    private function appointment(array $attributes = []): Rendezvous
    {
        $appointment = Rendezvous::factory()->create(array_merge(['statut' => 'confirmé', 'date_heure' => now()->addHours(20)], $attributes));
        $appointment->patient->user->forceFill(['status' => 'actif', 'email_verified_at' => now()])->save();
        $appointment->doctor->user->update(['status' => 'actif']);

        return $appointment;
    }

    private function mailChannel(bool $fail = false): object
    {
        $channel = new class($fail)
        {
            public int $attempts = 0;

            public function __construct(public bool $fail) {}

            public function send($notifiable, $notification): void
            {
                $this->attempts++;
                if ($this->fail) {
                    throw new \RuntimeException('uncertain fixture transport failure');
                }
            }
        };
        Notification::extend('mail', fn () => $channel);

        return $channel;
    }

    public function test_disabled_command_has_no_delivery_side_effects(): void
    {
        Queue::fake();
        config(['operations.appointment_reminders_enabled' => false]);
        $this->appointment();
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->assertDatabaseCount('appointment_reminders', 0);
        Queue::assertNothingPushed();
    }

    public function test_scan_catches_confirmed_visits_only_and_deduplicates_repeated_runs(): void
    {
        Queue::fake();
        $eligible = $this->appointment();
        foreach (['en_attente', 'annulé', 'terminé', 'no_show'] as $status) {
            $this->appointment(['statut' => $status]);
        }
        foreach ([-1, 0.25, 25] as $hours) {
            $this->appointment(['date_heure' => now()->addMinutes((int) ($hours * 60))]);
        }
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->assertDatabaseCount('appointment_reminders', 1);
        $this->assertDatabaseHas('appointment_reminders', ['appointment_id' => $eligible->id, 'mail_status' => 'pending']);
        Queue::assertPushed(DeliverAppointmentReminder::class, 2);
    }

    public function test_repeated_jobs_deliver_one_database_notification_and_one_mail_attempt(): void
    {
        Queue::fake();
        $channel = $this->mailChannel();
        $appointment = $this->appointment();
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $id = DB::table('appointment_reminders')->value('id');
        (new DeliverAppointmentReminder($id))->handle();
        (new DeliverAppointmentReminder($id))->handle();
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->assertSame(1, $channel->attempts);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $appointment->patient->user_id]);
        $this->assertDatabaseHas('appointment_reminders', ['id' => $id, 'mail_status' => 'sent']);
    }

    public function test_cancelled_rescheduled_late_and_ineligible_accounts_are_skipped_at_delivery(): void
    {
        Queue::fake();
        $channel = $this->mailChannel();
        foreach (['cancelled', 'rescheduled', 'late', 'patient_suspended', 'unverified', 'doctor_suspended'] as $change) {
            $appointment = $this->appointment();
            $this->artisan('appointments:send-reminders')->assertSuccessful();
            $id = DB::table('appointment_reminders')->where('appointment_id', $appointment->id)->value('id');
            match ($change) {
                'cancelled' => $appointment->update(['statut' => 'annulé']),
                'rescheduled' => $appointment->update(['date_heure' => now()->addHours(21)]),
                'late' => $appointment->update(['date_heure' => now()->addMinutes(10)]),
                'patient_suspended' => $appointment->patient->user->update(['status' => 'inactif']),
                'unverified' => $appointment->patient->user->forceFill(['email_verified_at' => null])->save(),
                'doctor_suspended' => $appointment->doctor->user->update(['status' => 'inactif']),
            };
            (new DeliverAppointmentReminder($id))->handle();
            $this->assertDatabaseHas('appointment_reminders', ['id' => $id, 'mail_status' => 'skipped']);
            $appointment->update(['statut' => 'annulé']);
        }
        $this->assertSame(0, $channel->attempts);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_uncertain_mail_failure_is_recorded_and_not_retried(): void
    {
        Queue::fake();
        $channel = $this->mailChannel(true);
        $this->appointment();
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $id = DB::table('appointment_reminders')->value('id');
        try {
            (new DeliverAppointmentReminder($id))->handle();
            $this->fail('Expected transport failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('uncertain fixture transport failure', $exception->getMessage());
        }
        (new DeliverAppointmentReminder($id))->handle();
        $this->assertSame(1, $channel->attempts);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('appointment_reminders', ['id' => $id, 'mail_status' => 'failed']);
    }

    public function test_disabled_worker_and_previously_claimed_mail_do_not_attempt_delivery(): void
    {
        Queue::fake();
        $channel = $this->mailChannel();
        $this->appointment();
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $id = DB::table('appointment_reminders')->value('id');
        config(['operations.appointment_reminders_enabled' => false]);
        (new DeliverAppointmentReminder($id))->handle();
        $this->assertDatabaseHas('appointment_reminders', ['id' => $id, 'mail_status' => 'pending']);
        config(['operations.appointment_reminders_enabled' => true]);
        DB::table('appointment_reminders')->where('id', $id)->update(['mail_status' => 'sending']);
        (new DeliverAppointmentReminder($id))->handle();
        $this->assertSame(0, $channel->attempts);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_changed_appointment_time_creates_a_new_reminder_and_template_uses_the_spa(): void
    {
        Queue::fake();
        $channel = $this->mailChannel();
        $appointment = $this->appointment();
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        (new DeliverAppointmentReminder(DB::table('appointment_reminders')->value('id')))->handle();
        $appointment->update(['date_heure' => now()->addHours(21)]);
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        (new DeliverAppointmentReminder(DB::table('appointment_reminders')->max('id')))->handle();
        $this->assertSame(2, $channel->attempts);
        $this->assertDatabaseCount('appointment_reminders', 2);
        config(['app.frontend_url' => 'https://preview.example/']);
        $mail = (new AppointmentReminderNotification($appointment, 'within_24_hours'))->toMail($appointment->patient->user);
        $this->assertSame('https://preview.example/patient/appointments', $mail->actionUrl);
        $this->assertStringContainsString($appointment->date_heure->format('H:i'), implode(' ', $mail->introLines));
    }
}
