<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Avis;
use App\Models\Doctor;
use App\Models\Payment;
use App\Models\Rendezvous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 'admin', 'status' => 'actif']);
        if (! $this->adminUser->admin) {
            Admin::factory()->active()->create(['user_id' => $this->adminUser->id]);
        } else {
            $this->adminUser->admin->update(['admin_status' => 1]);
        }
        Sanctum::actingAs($this->adminUser, ['role:admin']);
    }

    public function test_admin_can_access_dashboard_statistics(): void
    {
        User::factory(5)->create(['role' => 'patient']);
        $doctorUsers = User::factory(2)->create(['role' => 'medecin']);
        foreach ($doctorUsers as $docUser) {
            if (! $docUser->doctor) {
                Doctor::factory()->create(['user_id' => $docUser->id]);
            }
        }
        Avis::factory(3)->pending()->create();
        Rendezvous::factory(10)->create();
        Payment::factory()->create(['status' => 'completed', 'amount' => 1000]);
        Payment::factory()->create(['status' => 'completed', 'amount' => 1500]);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_users',
                    'total_doctors',
                    'total_patients',
                    'pending_reviews',
                    'total_appointments',
                    'total_revenue',
                    'recent_registrations',
                    'pending_doctor_verifications',
                ],
            ])
            ->assertJsonPath('data.total_users', User::count())
            ->assertJsonPath('data.total_doctors', User::where('role', 'medecin')->count())
            ->assertJsonPath('data.total_patients', User::where('role', 'patient')->count())
            ->assertJsonPath('data.pending_reviews', Avis::where('status', 'pending')->count())
            ->assertJsonPath('data.total_appointments', Rendezvous::count())
            ->assertJsonPath('data.total_revenue', Payment::where('status', 'completed')->sum('amount'));
    }

    public function test_admin_can_get_users_with_optional_filters(): void
    {
        User::factory(5)->create(['role' => 'patient']);
        User::factory(3)->create(['role' => 'medecin']);

        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(200)
            ->assertJsonCount(User::count(), 'data');

        $response = $this->getJson('/api/admin/users?role=patient');
        $response->assertStatus(200)
            ->assertJsonCount(User::where('role', 'patient')->count(), 'data');

        $patientToSearch = User::factory()->create(['role' => 'patient', 'nom' => 'SearchablePatientName']);
        $response = $this->getJson('/api/admin/users?search=SearchablePatientName');
        $response->assertStatus(200)
            ->assertJsonFragment(['nom' => 'SearchablePatientName']);
    }

    public function test_admin_can_update_user_status(): void
    {
        $userToUpdate = User::factory()->create(['status' => 'actif']);

        $response = $this->putJson("/api/admin/users/{$userToUpdate->id}/status", $this->statusDecision($userToUpdate, ['status' => 'inactif']));

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'User status updated successfully',
                'user' => [
                    'id' => $userToUpdate->id,
                    'status' => 'inactif',
                ],
            ]);
        $this->assertDatabaseHas('users', ['id' => $userToUpdate->id, 'status' => 'inactif']);
    }

    public function test_admin_can_get_pending_reviews(): void
    {
        Avis::factory(3)->pending()->create();
        Avis::factory(2)->approved()->create();

        $response = $this->getJson('/api/admin/reviews/pending');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_moderate_review_to_approve(): void
    {
        $review = Avis::factory()->pending()->create();

        $response = $this->postJson("/api/admin/reviews/{$review->id}/moderate", [
            'action' => 'approve',
            'expected_status' => 'pending',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Avis modéré avec succès',
                'review' => [
                    'id' => $review->id,
                    'status' => 'approved',
                    'moderated_by' => $this->adminUser->id,
                ],
            ]);
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => 'approved']);
    }

    public function test_admin_can_moderate_review_to_reject_with_reason(): void
    {
        $review = Avis::factory()->pending()->create();
        $rejectionReason = 'This review violates community guidelines.';

        $response = $this->postJson("/api/admin/reviews/{$review->id}/moderate", [
            'action' => 'reject',
            'expected_status' => 'pending',
            'reason' => $rejectionReason,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Avis modéré avec succès',
                'review' => [
                    'id' => $review->id,
                    'status' => 'rejected',
                    'moderated_by' => $this->adminUser->id,
                ],
            ]);
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => 'rejected']);
    }

    public function test_admin_can_export_user_data_as_csv(): void
    {
        User::factory(3)->create();

        $response = $this->get('/api/admin/users/export?format=csv');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="users_export.csv"');

        $content = $response->streamedContent();
        ob_start();
        echo $content;
        $csvOutput = ob_get_clean();

        // Trim the CSV output to remove potential leading/trailing whitespace including BOM
        $csvOutput = trim($csvOutput);

        // Check if the header row is present at the beginning of the string
        $this->assertStringStartsWith('ID,Nom,Prénom,Email,Rôle,Statut,"Date d\'inscription"', $csvOutput);
        foreach (User::all() as $user) {
            if ($user->id === $this->adminUser->id) {
                continue;
            } // Skip the admin user used for auth if it's among the created ones
            $this->assertStringContainsString($user->email, $csvOutput);
        }
    }

    public function test_admin_export_user_data_returns_json_for_unsupported_format(): void
    {
        $response = $this->getJson('/api/admin/users/export?format=excel');

        $response->assertStatus(501)
            ->assertJson([
                'message' => 'Excel export is not yet implemented. Please use CSV format.',
                'requested_format' => 'excel',
            ]);
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $patientUser = User::factory()->create(['role' => 'patient']);
        Sanctum::actingAs($patientUser, ['role:patient']);

        $response = $this->getJson('/api/admin/dashboard');
        $response->assertStatus(403);

        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(403);
    }

    public function test_inactive_admin_cannot_access_admin_routes_that_check_permission(): void
    {
        $inactiveAdminUser = User::factory()->create(['role' => 'admin', 'status' => 'actif']);
        Admin::factory()->inactive()->create(['user_id' => $inactiveAdminUser->id]);

        Sanctum::actingAs($inactiveAdminUser, ['role:admin']);

        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(403)
            ->assertJson(['message' => 'Administrator access is disabled.']);
    }
}
