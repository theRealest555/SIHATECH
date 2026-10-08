<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Language;
use App\Models\Speciality;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogueControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create(['role' => 'admin', 'status' => 'actif', 'email_verified_at' => now()]);
        Admin::factory()->active()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);
    }

    public function test_speciality_creation_edit_and_public_data_are_real_and_audited(): void
    {
        $id = $this->postJson('/api/admin/specialities', ['nom' => 'Clinical test', 'description' => 'Care description'])->assertCreated()->json('data.id');
        $this->getJson('/api/admin/audit-logs')->assertOk()->assertJsonPath('data.0.target.type', 'Speciality');
        $doctor = Doctor::factory()->create(['speciality_id' => $id]);
        $doctor->delete();
        $this->getJson('/api/admin/specialities?search=Clinical')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.doctors_count', 1);
        $this->putJson('/api/admin/specialities/'.$id, ['nom' => 'Updated clinical test', 'description' => 'Updated care', 'expected_nom' => 'Clinical test', 'expected_description' => 'Care description'])->assertOk();
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id, 'speciality_id' => $id]);
        $this->getJson('/api/public/specialities')->assertOk()->assertJsonFragment(['nom' => 'Updated clinical test', 'description' => 'Updated care']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'created_catalogue_record', 'target_id' => $id, 'target_type' => Speciality::class]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'updated_catalogue_record', 'target_id' => $id, 'target_type' => Speciality::class]);
    }

    public function test_language_edit_preserves_assignments_and_rejects_stale_edits(): void
    {
        $id = $this->postJson('/api/admin/languages', ['nom' => 'Preview language'])->assertCreated()->json('data.id');
        $doctor = Doctor::factory()->create();
        $doctor->languages()->attach($id);
        $this->putJson('/api/admin/languages/'.$id, ['nom' => 'Updated language', 'expected_nom' => 'Preview language'])->assertOk();
        $this->putJson('/api/admin/languages/'.$id, ['nom' => 'Stale language', 'expected_nom' => 'Preview language'])->assertConflict();
        $this->assertDatabaseHas('doctor_language', ['doctor_id' => $doctor->id, 'language_id' => $id]);
        $this->getJson('/api/public/languages')->assertOk()->assertJsonFragment(['nom' => 'Updated language']);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_validation_duplicates_and_database_uniqueness(): void
    {
        $this->postJson('/api/admin/languages', ['nom' => 'French test'])->assertCreated();
        $this->postJson('/api/admin/languages', ['nom' => 'French test'])->assertUnprocessable();
        $this->postJson('/api/admin/languages', ['nom' => ' '])->assertUnprocessable();
        $this->postJson('/api/admin/specialities', ['nom' => 'Missing description'])->assertUnprocessable();
        $this->getJson('/api/admin/languages?per_page=101')->assertUnprocessable();
        $this->expectException(UniqueConstraintViolationException::class);
        Language::create(['nom' => 'French test']);
    }

    public function test_listing_escapes_search_wildcards_and_paginates(): void
    {
        Language::create(['nom' => 'Test_100%']);
        Language::create(['nom' => 'Other']);
        $this->getJson('/api/admin/languages?search=%25')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.nom', 'Test_100%');
        $this->getJson('/api/admin/languages?per_page=1')->assertOk()->assertJsonPath('meta.last_page', 2)->assertJsonPath('data.0.nom', 'Other');
    }

    public function test_changes_roll_back_on_audit_failure(): void
    {
        AuditLog::creating(function () {
            throw new \RuntimeException('Audit unavailable');
        });
        try {
            $this->postJson('/api/admin/languages', ['nom' => 'Must roll back'])->assertStatus(500);
            $this->assertDatabaseMissing('languages', ['nom' => 'Must roll back']);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_unique_name_migration_refuses_historical_duplicates_without_removing_rows(): void
    {
        Schema::table('languages', fn ($table) => $table->dropUnique(['nom']));
        Language::create(['nom' => 'Historical duplicate']);
        Language::create(['nom' => 'Historical duplicate']);
        $migration = require database_path('migrations/2026_10_07_000002_unique_catalogue_names.php');
        try {
            $migration->up();
            $this->fail('Duplicate names should prevent migration');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Duplicate names in languages', $exception->getMessage());
            $this->assertSame(2, Language::where('nom', 'Historical duplicate')->count());
        }
    }

    public function test_non_admins_cannot_manage_catalogues(): void
    {
        foreach (['patient', 'medecin'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'status' => 'actif', 'email_verified_at' => now()]));
            $this->getJson('/api/admin/languages')->assertForbidden();
            $this->postJson('/api/admin/specialities', ['nom' => 'Forbidden', 'description' => 'Forbidden'])->assertForbidden();
        }
    }
}
