<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Avis;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Rendezvous;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewJourneyTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;

    private Doctor $doctor;

    private Rendezvous $visit;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory(3)->create();
        $user = User::factory()->create(['role' => 'patient', 'status' => 'actif', 'email_verified_at' => now()]);
        $this->patient = Patient::factory()->create(['user_id' => $user->id]);
        $this->doctor = Doctor::factory()->create(['average_rating' => 0, 'total_reviews' => 0]);
        $this->visit = Rendezvous::factory()->create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'statut' => 'terminé', 'date_heure' => now()->subDay()]);
        Sanctum::actingAs($user);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'actif', 'email_verified_at' => now()]);
        Admin::factory()->active()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function submit(int $rating = 4): Avis
    {
        $id = $this->postJson('/api/patient/appointments/'.$this->visit->id.'/review', ['rating' => $rating, 'comment' => 'Clear and helpful communication', 'status' => 'approved', 'doctor_id' => 999])->assertCreated()->json('review.id');

        return Avis::findOrFail($id);
    }

    public function test_submission_uses_profile_ownership_and_server_identity_and_prevents_duplicates(): void
    {
        $this->getJson('/api/patient/appointments/'.$this->visit->id.'/review')->assertOk()->assertJsonPath('data.can_submit', true);
        $review = $this->submit();
        $this->assertSame($this->patient->user_id, $review->patient_id);
        $this->assertSame($this->doctor->user_id, $review->doctor_id);
        $this->assertSame($this->visit->id, $review->rendezvous_id);
        $this->assertSame('pending', $review->status);
        $this->postJson('/api/patient/appointments/'.$this->visit->id.'/review', ['rating' => 5])->assertConflict();
        $this->getJson('/api/patient/appointments/'.$this->visit->id.'/review')->assertOk()->assertJsonPath('data.can_submit', false)->assertJsonPath('data.review.status', 'pending');
        $this->assertSame(0, $this->doctor->fresh()->total_reviews);
    }

    public function test_foreign_and_ineligible_appointments_are_not_reviewable(): void
    {
        $other = Rendezvous::factory()->create();
        $this->getJson('/api/patient/appointments/'.$other->id.'/review')->assertNotFound();
        $this->postJson('/api/patient/appointments/'.$other->id.'/review', ['rating' => 4])->assertNotFound();
        foreach (['en_attente', 'confirmé', 'annulé', 'no_show'] as $status) {
            $this->visit->update(['statut' => $status]);
            $this->postJson('/api/patient/appointments/'.$this->visit->id.'/review', ['rating' => 4])->assertConflict();
        }
        $this->visit->update(['statut' => 'terminé', 'date_heure' => now()->addDay()]);
        $this->postJson('/api/patient/appointments/'.$this->visit->id.'/review', ['rating' => 4])->assertConflict();
    }

    public function test_invalid_ratings_and_oversized_comments_are_rejected(): void
    {
        foreach ([0, 6, 2.5] as $rating) {
            $this->postJson('/api/patient/appointments/'.$this->visit->id.'/review', ['rating' => $rating])->assertUnprocessable();
        }
        $this->postJson('/api/patient/appointments/'.$this->visit->id.'/review', ['rating' => 3, 'comment' => str_repeat('a', 2001)])->assertUnprocessable();
    }

    public function test_moderation_updates_rating_audit_and_rejection_reason_atomically(): void
    {
        $review = $this->submit();
        $admin = $this->admin();
        $this->postJson('/api/admin/reviews/'.$review->id.'/moderate', ['action' => 'approve', 'expected_status' => 'pending'])->assertOk();
        $this->assertSame(4.0, $this->doctor->fresh()->average_rating);
        $this->assertSame(1, $this->doctor->fresh()->total_reviews);
        $this->postJson('/api/admin/reviews/'.$review->id.'/moderate', ['action' => 'reject', 'expected_status' => 'pending', 'reason' => 'Stale decision'])->assertConflict();
        $this->postJson('/api/admin/reviews/'.$review->id.'/moderate', ['action' => 'reject', 'expected_status' => 'approved', 'reason' => 'Contains inappropriate details'])->assertOk();
        $this->assertSame(0.0, $this->doctor->fresh()->average_rating);
        $this->assertSame(0, $this->doctor->fresh()->total_reviews);
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'moderated_by' => $admin->id, 'moderation_reason' => 'Contains inappropriate details']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'approved_review', 'target_id' => $review->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rejected_review', 'target_id' => $review->id]);
        Sanctum::actingAs($this->patient->user);
        $this->getJson('/api/patient/reviews')->assertOk()->assertJsonPath('data.0.reason', 'Contains inappropriate details');
    }

    public function test_moderation_requires_reason_and_original_status_and_is_idempotent(): void
    {
        $review = $this->submit();
        $this->admin();
        $this->postJson('/api/admin/reviews/'.$review->id.'/moderate', ['action' => 'approve'])->assertUnprocessable();
        $this->postJson('/api/admin/reviews/'.$review->id.'/moderate', ['action' => 'reject', 'expected_status' => 'pending'])->assertUnprocessable();
        $this->postJson('/api/admin/reviews/'.$review->id.'/moderate', ['action' => 'approve', 'expected_status' => 'pending'])->assertOk();
        $this->postJson('/api/admin/reviews/'.$review->id.'/moderate', ['action' => 'approve', 'expected_status' => 'approved'])->assertOk();
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_invalid_legacy_context_cannot_be_approved_but_can_be_rejected(): void
    {
        $review = $this->submit();
        $review->update(['rendezvous_id' => null]);
        $this->admin();
        $this->postJson('/api/admin/reviews/'.$review->id.'/moderate', ['action' => 'approve', 'expected_status' => 'pending'])->assertConflict();
        $this->postJson('/api/admin/reviews/'.$review->id.'/moderate', ['action' => 'reject', 'expected_status' => 'pending', 'reason' => 'Missing verified appointment'])->assertOk();
    }

    public function test_audit_failure_rolls_back_moderation_and_rating(): void
    {
        $review = $this->submit();
        $this->admin();
        AuditLog::creating(function () {
            throw new \RuntimeException('Audit unavailable');
        });
        try {
            $this->postJson('/api/admin/reviews/'.$review->id.'/moderate', ['action' => 'approve', 'expected_status' => 'pending'])->assertStatus(500);
            $this->assertSame('pending', $review->fresh()->status);
            $this->assertSame(0, $this->doctor->fresh()->total_reviews);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_rating_is_the_average_of_approved_reviews_not_the_latest_score(): void
    {
        $first = $this->submit(4);
        $this->visit = Rendezvous::factory()->create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'statut' => 'terminé', 'date_heure' => now()->subDays(2)]);
        $second = $this->submit(2);
        $this->admin();
        foreach ([$first, $second] as $review) {
            $this->postJson('/api/admin/reviews/'.$review->id.'/moderate', ['action' => 'approve', 'expected_status' => 'pending'])->assertOk();
        }
        $this->assertSame(3.0, $this->doctor->fresh()->average_rating);
        $this->assertSame(2, $this->doctor->fresh()->total_reviews);
    }

    public function test_review_unique_migration_stops_on_historical_duplicates(): void
    {
        $review = $this->submit();
        Schema::table('reviews', fn ($table) => $table->dropUnique(['rendezvous_id']));
        Avis::create(['patient_id' => $review->patient_id, 'doctor_id' => $review->doctor_id, 'rendezvous_id' => $review->rendezvous_id, 'rating' => 3, 'status' => 'pending']);
        $migration = require database_path('migrations/2026_10_07_000003_review_integrity.php');
        try {
            $migration->up();
            $this->fail('Duplicate reviews must prevent migration');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Duplicate appointment reviews', $exception->getMessage());
            $this->assertSame(2, Avis::where('rendezvous_id', $review->rendezvous_id)->count());
        }
    }

    public function test_database_rejects_duplicate_reviews_for_the_same_appointment(): void
    {
        $review = $this->submit();
        $this->expectException(UniqueConstraintViolationException::class);
        Avis::create(['patient_id' => $review->patient_id, 'doctor_id' => $review->doctor_id, 'rendezvous_id' => $review->rendezvous_id, 'rating' => 3, 'status' => 'pending']);
    }

    public function test_review_lists_do_not_expose_contact_or_account_data_and_are_owner_scoped(): void
    {
        $this->submit();
        Avis::factory()->pending()->create();
        $this->getJson('/api/patient/reviews')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonMissingPath('data.0.patient')->assertJsonMissingPath('data.0.email');
        $this->getJson('/api/admin/reviews')->assertForbidden();
        $this->admin();
        $this->getJson('/api/admin/reviews?status=pending&per_page=1')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.doctor.email');
    }
}
