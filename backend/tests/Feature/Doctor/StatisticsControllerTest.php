<?php

namespace Tests\Feature\Doctor;

use App\Models\Avis;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Rendezvous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StatisticsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 6)->startOfDay());
        $user = User::factory()->create(['role' => 'medecin', 'email_verified_at' => now(), 'status' => 'actif']);
        $this->doctor = Doctor::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user, ['role:medecin']);
    }

    private function visit(string $date, string $status, ?Patient $patient = null, ?Doctor $doctor = null): void
    {
        Rendezvous::factory()->create(['doctor_id' => ($doctor ?? $this->doctor)->id, 'patient_id' => ($patient ?? Patient::factory()->create())->id, 'date_heure' => $date, 'statut' => $status]);
    }

    public function test_period_counts_are_scoped_and_patients_seen_require_completion(): void
    {
        $patient = Patient::factory()->create();
        $this->visit('2026-10-01 00:00:00', 'terminé', $patient);
        $this->visit('2026-10-31 23:59:59', 'terminé', $patient);
        $this->visit('2026-10-02 12:00:00', 'en_attente');
        $this->visit('2026-09-30 23:59:59', 'terminé');
        $this->visit('2026-11-01 00:00:00', 'confirmé');
        $this->visit('2026-10-05 12:00:00', 'terminé', null, Doctor::factory()->create());
        $this->getJson('/api/doctor/stats')->assertOk()->assertJsonPath('data.appointments.total', 3)
            ->assertJsonPath('data.appointments.by_status.terminé', 2)->assertJsonPath('data.patients.total_unique', 2)
            ->assertJsonPath('data.patients.seen', 1)->assertJsonPath('data.patients.repeat_completed', 1)
            ->assertJsonPath('data.trends.appointments.0.count', 1)->assertJsonPath('data.trends.appointments.30.count', 1)
            ->assertJsonCount(31, 'data.trends.appointments')->assertJsonPath('data.rating.average', null)
            ->assertJsonPath('data.revenue.available', false)->assertJsonMissingPath('data.performance');
    }

    public function test_week_and_custom_filters_are_honoured_by_all_summary_endpoints(): void
    {
        $this->visit('2026-10-04 09:00:00', 'annulé');
        $this->visit('2026-10-05 09:00:00', 'no_show');
        $this->getJson('/api/doctor/stats/appointments?period=week')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.by_status.no_show', 1);
        $this->getJson('/api/doctor/stats/patients?start_date=2026-10-04&end_date=2026-10-04')->assertOk()->assertJsonPath('data.total_unique', 1)->assertJsonPath('data.seen', 0);
        $this->getJson('/api/doctor/stats?start_date=2026-10-03&end_date=2026-10-03')->assertOk()->assertJsonPath('data.appointments.total', 0)->assertJsonCount(1, 'data.trends.appointments');
    }

    public function test_invalid_partial_reversed_and_unbounded_ranges_are_rejected(): void
    {
        foreach (['start_date=2026-10-01', 'end_date=2026-10-01', 'start_date=2026-10-02&end_date=2026-10-01', 'start_date=2025-01-01&end_date=2026-10-01', 'period=all', 'start_date=2026-02-30&end_date=2026-03-01'] as $filter) {
            $this->getJson('/api/doctor/stats?'.$filter)->assertUnprocessable();
        }
    }

    public function test_export_uses_inclusive_range_and_real_status_keys_without_patient_details(): void
    {
        $this->visit('2026-10-01 12:00:00', 'en_attente');
        $this->visit('2026-10-01 13:00:00', 'terminé');
        $this->visit('2026-10-02 13:00:00', 'terminé');
        $response = $this->get('/api/doctor/stats/export?type=overview&start_date=2026-10-01&end_date=2026-10-01');
        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('en_attente,1', $csv);
        $this->assertStringContainsString('terminé,1', $csv);
        $this->assertStringContainsString('2026-10-01', $csv);
        $this->assertStringNotContainsString('@', $csv);
        $this->assertStringNotContainsString('Revenue', $csv);
    }

    public function test_unsupported_exports_do_not_fabricate_revenue(): void
    {
        $this->getJson('/api/doctor/stats/revenue?year=2025')->assertOk()->assertJsonPath('data.available', false)->assertJsonMissingPath('data.total_this_year');
        $this->getJson('/api/doctor/stats/export?type=revenue')->assertUnprocessable();
        $this->getJson('/api/doctor/stats/export?type=overview&format=pdf')->assertUnprocessable();
    }

    public function test_rating_only_uses_approved_reviews_in_range_for_this_doctor(): void
    {
        $patient = Patient::factory()->create();
        foreach ([['approved', 4, '2026-10-01'], ['approved', 2, '2026-10-02'], ['pending', 5, '2026-10-03'], ['rejected', 1, '2026-10-04'], ['approved', 5, '2026-09-30']] as [$status, $rating, $date]) {
            Avis::create(['patient_id' => $patient->user_id, 'doctor_id' => $this->doctor->user_id, 'rating' => $rating, 'comment' => 'Test review', 'status' => $status, 'created_at' => $date]);
            Avis::latest('id')->first()->forceFill(['created_at' => $date.' 12:00:00'])->save();
        }
        $other = Doctor::factory()->create();
        Avis::create(['patient_id' => $patient->user_id, 'doctor_id' => $other->user_id, 'rating' => 5, 'comment' => 'Other doctor', 'status' => 'approved']);
        $this->getJson('/api/doctor/stats')->assertOk()->assertJsonPath('data.rating.total_reviews', 2)->assertJsonPath('data.rating.average', 3);
    }

    public function test_patients_cannot_access_doctor_statistics_or_exports(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'patient', 'email_verified_at' => now(), 'status' => 'actif']), ['role:patient']);
        $this->getJson('/api/doctor/stats')->assertForbidden();
        $this->getJson('/api/doctor/stats/export?type=overview')->assertForbidden();
    }
}
