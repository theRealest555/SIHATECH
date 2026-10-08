<?php

namespace Tests\Feature;

use App\Jobs\MarkNoShowAppointments;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Rendezvous;
use App\Notifications\AppointmentNoShowNotification;
use App\Services\AttendanceAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceDecisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
    }

    private function appointment(array $attributes = []): Rendezvous
    {
        $doctor = Doctor::factory()->create(['is_verified' => true]);

        return Rendezvous::factory()->create(array_merge(['doctor_id' => $doctor->id, 'statut' => 'confirmé', 'date_heure' => now()->subHour()], $attributes));
    }

    public function test_legacy_job_and_audit_leave_attendance_unchanged_and_send_nothing(): void
    {
        Notification::fake();
        foreach (['confirmé', 'en_attente', 'terminé', 'annulé', 'no_show'] as $status) {
            $this->appointment(['statut' => $status]);
        }
        $this->appointment(['date_heure' => now()->subMinutes(30)]);
        $this->appointment(['date_heure' => now()->addHour()]);
        $expected = ['confirmed_needing_review' => 1, 'unconfirmed_past_requests' => 1];
        $this->assertSame($expected, app(AttendanceAudit::class)->counts());
        $this->artisan('appointments:audit-attendance', ['--json' => true])->expectsOutput(json_encode($expected))->assertExitCode(1);
        (new MarkNoShowAppointments)->handle();
        (new MarkNoShowAppointments)->handle();
        $this->assertSame(3, Rendezvous::where('statut', 'confirmé')->count());
        $this->assertSame(1, Rendezvous::where('statut', 'en_attente')->count());
        $this->assertSame(1, Rendezvous::where('statut', 'no_show')->count());
        $this->assertDatabaseCount('audit_logs', 0);
        Notification::assertNothingSent();
    }

    public function test_doctor_decision_is_audited_once_and_cannot_be_repeated(): void
    {
        Notification::fake();
        $appointment = $this->appointment();
        Sanctum::actingAs($appointment->doctor->user);
        $path = '/api/doctor/appointments/'.$appointment->id.'/no-show';
        $this->postJson($path, ['reason' => 'private fixture reason'])->assertOk()->assertJsonPath('data.statut', 'no_show');
        $this->postJson($path)->assertConflict();
        $this->assertDatabaseCount('audit_logs', 1);
        $log = AuditLog::first();
        $this->assertSame('marked_appointment_no_show', $log->action);
        $this->assertSame($appointment->doctor->user_id, $log->user_id);
        $this->assertSame($appointment->id, $log->target_id);
        $this->assertStringNotContainsString('private fixture reason', $log->metadata);
        Notification::assertNothingSent();
    }

    public function test_unconfirmed_and_final_visits_cannot_be_rewritten(): void
    {
        foreach (['en_attente', 'terminé', 'annulé', 'no_show'] as $status) {
            $appointment = $this->appointment(['statut' => $status]);
            Sanctum::actingAs($appointment->doctor->user);
            $this->postJson('/api/doctor/appointments/'.$appointment->id.'/no-show')->assertConflict();
            $this->assertSame($status, $appointment->fresh()->statut);
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_future_visits_and_other_doctors_and_patients_cannot_mark_attendance(): void
    {
        $appointment = $this->appointment(['date_heure' => now()->addHour()]);
        Sanctum::actingAs($appointment->doctor->user);
        $this->postJson('/api/doctor/appointments/'.$appointment->id.'/no-show')->assertStatus(400);
        $appointment->update(['date_heure' => now()->subHour()]);
        Sanctum::actingAs(Doctor::factory()->create(['is_verified' => true])->user);
        $this->postJson('/api/doctor/appointments/'.$appointment->id.'/no-show')->assertForbidden();
        Sanctum::actingAs($appointment->patient->user);
        $this->postJson('/api/doctor/appointments/'.$appointment->id.'/no-show')->assertForbidden();
        $this->assertSame('confirmé', $appointment->fresh()->statut);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_failure_rolls_back_the_attendance_decision(): void
    {
        $appointment = $this->appointment();
        Sanctum::actingAs($appointment->doctor->user);
        AuditLog::creating(fn () => throw new \RuntimeException('fixture audit failure'));
        $this->postJson('/api/doctor/appointments/'.$appointment->id.'/no-show')->assertStatus(500);
        $this->assertSame('confirmé', $appointment->fresh()->statut);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_clean_attendance_audit_succeeds(): void
    {
        $this->appointment(['statut' => 'terminé']);
        $this->artisan('appointments:audit-attendance', ['--json' => true])
            ->expectsOutput('{"confirmed_needing_review":0,"unconfirmed_past_requests":0}')->assertSuccessful();
    }

    public function test_retained_notice_uses_the_frontend_without_an_invented_cancellation_deadline(): void
    {
        config(['app.frontend_url' => 'https://preview.example/']);
        $appointment = $this->appointment();
        $mail = (new AppointmentNoShowNotification($appointment))->toMail($appointment->patient->user);
        $this->assertSame('https://preview.example/doctors/'.$appointment->doctor_id, $mail->actionUrl);
        $this->assertStringNotContainsString('24h', implode(' ', $mail->introLines));
    }
}
