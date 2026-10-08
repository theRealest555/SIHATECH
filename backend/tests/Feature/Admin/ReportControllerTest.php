<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Payment;
use App\Models\Rendezvous;
use App\Models\Speciality;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $range = '?start_date=2026-10-01&end_date=2026-10-03';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 7)->startOfDay());
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'actif', 'email_verified_at' => now()]);
        Admin::factory()->active()->create(['user_id' => $this->admin->id]);
        Sanctum::actingAs($this->admin);
    }

    private function payment(array $changes = []): Payment
    {
        $payment = Payment::create(array_replace(['user_id' => $this->admin->id, 'user_subscription_id' => null, 'transaction_id' => fake()->uuid(), 'amount' => '100.00', 'currency' => 'MAD', 'status' => 'completed', 'payment_method' => 'stripe', 'payment_data' => ['secret' => 'private-provider-data']], $changes));
        $payment->forceFill(['created_at' => '2026-10-03 23:59:59'])->save();

        return $payment;
    }

    public function test_financial_totals_separate_currencies_and_exclude_failed_amounts(): void
    {
        $subscription = UserSubscription::factory()->create(['created_at' => '2026-10-02 10:00:00', 'status' => 'cancelled', 'cancelled_at' => '2026-10-03 23:59:59']);
        $this->payment(['amount' => '199.10', 'user_subscription_id' => $subscription->id]);
        $this->payment(['amount' => '0.20', 'currency' => 'mad']);
        $this->payment(['amount' => '10.00', 'currency' => 'USD']);
        $this->payment(['amount' => '999.00', 'status' => 'failed']);
        $this->payment()->forceFill(['created_at' => '2026-10-04 00:00:00'])->save();
        $response = $this->getJson('/api/admin/reports/financial'.$this->range)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $response->assertJsonPath('data.payments.total', 4)->assertJsonPath('data.payments.by_status.completed', 3)->assertJsonPath('data.payments.by_status.failed', 1)
            ->assertJsonPath('data.payments.by_currency.0.currency', 'MAD')->assertJsonPath('data.payments.by_currency.0.completed_amount', '199.30')->assertJsonPath('data.payments.by_currency.0.subscription_amount', '199.10')
            ->assertJsonPath('data.payments.by_currency.1.currency', 'USD')->assertJsonPath('data.payments.by_currency.1.completed_amount', '10.00')
            ->assertJsonPath('data.subscriptions.cancelled_in_range', 1)->assertJsonPath('data.range.date_basis', 'payment_record_created_at');
        $response->assertDontSee($this->admin->email)->assertDontSee('private-provider-data')->assertDontSee('transaction_id');
    }

    public function test_current_active_snapshot_excludes_expired_and_future_windows(): void
    {
        foreach ([['status' => 'active', 'starts_at' => '2026-10-01', 'ends_at' => '2026-11-01'], ['status' => 'active', 'starts_at' => '2026-09-01', 'ends_at' => '2026-10-01'], ['status' => 'active', 'starts_at' => '2026-11-01', 'ends_at' => '2026-12-01'], ['status' => 'pending', 'starts_at' => '2026-10-01', 'ends_at' => '2026-11-01']] as $attributes) {
            UserSubscription::factory()->create(array_merge($attributes, ['created_at' => '2026-09-01']));
        }
        $this->getJson('/api/admin/reports/financial'.$this->range)->assertOk()->assertJsonPath('data.subscriptions.active_now', 1)->assertJsonPath('data.subscriptions.created_in_range', 0)->assertJsonPath('data.payments.total', 0)->assertJsonPath('data.payments.by_currency', []);
    }

    public function test_appointment_reports_use_scheduled_dates_include_archived_profiles_and_fill_gaps(): void
    {
        $speciality = Speciality::factory()->create(['nom' => 'Cardiology']);
        $doctor = Doctor::factory()->create(['speciality_id' => $speciality->id]);
        Rendezvous::factory()->create(['doctor_id' => $doctor->id, 'statut' => 'terminé', 'date_heure' => '2026-10-01 09:00:00', 'created_at' => '2026-09-01']);
        Rendezvous::factory()->create(['doctor_id' => $doctor->id, 'statut' => 'annulé', 'date_heure' => '2026-10-03 23:59:59', 'created_at' => '2026-09-01']);
        Rendezvous::factory()->create(['doctor_id' => $doctor->id, 'statut' => 'no_show', 'date_heure' => '2026-10-03 09:30:00', 'created_at' => '2026-09-01']);
        Rendezvous::factory()->create(['doctor_id' => $doctor->id, 'statut' => 'confirmé', 'date_heure' => '2026-10-04 00:00:00', 'created_at' => '2026-10-01']);
        $doctor->delete();
        $this->getJson('/api/admin/reports/appointments'.$this->range)->assertOk()->assertJsonPath('data.appointments.total', 3)->assertJsonPath('data.appointments.by_status.terminé', 1)->assertJsonPath('data.appointments.by_status.annulé', 1)->assertJsonPath('data.appointments.by_status.no_show', 1)->assertJsonPath('data.specialities.0.count', 3)->assertJsonPath('data.daily.1.count', 0)->assertJsonPath('data.daily.2.count', 2)->assertJsonPath('data.hourly.9.count', 2)->assertJsonPath('data.hourly.23.count', 1);
    }

    public function test_empty_reports_and_date_validation_are_consistent_for_reads_and_exports(): void
    {
        $this->getJson('/api/admin/reports/appointments'.$this->range)->assertOk()->assertJsonPath('data.appointments.total', 0)->assertJsonCount(3, 'data.daily')->assertJsonCount(24, 'data.hourly');
        foreach (['financial', 'appointments', 'export/financial', 'export/appointments'] as $route) {
            foreach (['?start_date=not-a-date&end_date=2026-10-03', '?start_date=2026-10-01', '?start_date=2026-10-03&end_date=2026-10-01', '?start_date=2025-01-01&end_date=2026-01-02'] as $query) {
                $this->getJson('/api/admin/reports/'.$route.$query)->assertUnprocessable();
            }
        }
        $this->getJson('/api/admin/reports/export/financial?format=xlsx')->assertUnprocessable();
    }

    public function test_financial_csv_matches_aggregate_totals_and_contains_no_account_details(): void
    {
        $this->payment(['amount' => '150.10']);
        $this->payment(['amount' => '150.20']);
        $response = $this->get('/api/admin/reports/export/financial'.$this->range)->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('completed_amount,300.30,MAD', $csv);
        $this->assertStringContainsString('"End date (inclusive)",2026-10-03', $csv);
        $this->assertStringNotContainsString($this->admin->email, $csv);
        $this->assertStringNotContainsString('private-provider-data', $csv);
    }

    public function test_appointment_csv_neutralizes_formula_names_and_exports_the_same_range(): void
    {
        $speciality = Speciality::factory()->create(['nom' => '=1+1']);
        $doctor = Doctor::factory()->create(['speciality_id' => $speciality->id]);
        Rendezvous::factory()->create(['doctor_id' => $doctor->id, 'date_heure' => '2026-10-02 09:00:00', 'statut' => 'confirmé']);
        $csv = $this->get('/api/admin/reports/export/appointments'.$this->range)->assertOk()->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString('Appointments,Total,1,', $csv);
        $this->assertStringContainsString('2026-10-02,1,', $csv);
    }

    public function test_reports_and_exports_require_an_approved_administrator(): void
    {
        foreach (['patient', 'medecin'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'status' => 'actif', 'email_verified_at' => now()]));
            foreach (['financial', 'appointments', 'export/financial', 'export/appointments'] as $route) {
                $this->getJson('/api/admin/reports/'.$route)->assertForbidden();
            }
        }
        $this->admin->admin->update(['admin_status' => false]);
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/admin/reports/financial')->assertForbidden();
    }
}
