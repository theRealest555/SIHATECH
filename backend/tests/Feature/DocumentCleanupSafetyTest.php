<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Document;
use App\Models\DocumentFileCleanup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentCleanupSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function document(): Document
    {
        Storage::fake('documents');
        $user = User::factory()->create(['role' => 'medecin', 'status' => 'actif', 'email_verified_at' => now()]);
        $doctor = Doctor::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);
        $document = Document::factory()->create(['doctor_id' => $doctor->id, 'status' => 'pending', 'file_path' => 'doctor-documents/cleanup.pdf']);
        Storage::disk('documents')->put($document->file_path, 'private fixture');

        return $document;
    }

    public function test_record_deletion_failure_preserves_file_and_rolls_back_cleanup_request(): void
    {
        $document = $this->document();
        Document::deleting(fn () => throw new \RuntimeException('Fixture database failure'));
        try {
            $this->deleteJson('/api/doctor/documents/'.$document->id)->assertStatus(500);
            $this->assertDatabaseHas('documents', ['id' => $document->id]);
            $this->assertDatabaseCount('document_file_cleanups', 0);
            Storage::disk('documents')->assertExists($document->file_path);
        } finally {
            Document::flushEventListeners();
        }
    }

    public function test_storage_failure_keeps_durable_cleanup_for_later_retry(): void
    {
        $document = $this->document();
        $this->deleteJson('/api/doctor/documents/'.$document->id)->assertOk()->assertJsonPath('file_cleanup_pending', true);
        $disk = Storage::disk('documents');
        $failing = \Mockery::mock($disk)->makePartial();
        $failing->shouldReceive('exists')->with($document->file_path)->andReturn(true);
        $failing->shouldReceive('delete')->with($document->file_path)->andReturn(false);
        Storage::set('documents', $failing);
        $this->artisan('documents:cleanup-files')->assertFailed();
        $this->assertDatabaseHas('document_file_cleanups', ['file_path' => $document->file_path, 'attempts' => 1, 'last_error' => 'storage_failure']);
        Storage::set('documents', $disk);
        $this->artisan('documents:cleanup-files')->assertSuccessful();
        $this->assertDatabaseCount('document_file_cleanups', 0);
        $disk->assertMissing($document->file_path);
    }

    public function test_cleanup_refuses_referenced_and_unsafe_paths(): void
    {
        $document = $this->document();
        DocumentFileCleanup::create(['file_path' => $document->file_path]);
        DocumentFileCleanup::create(['file_path' => '../outside.pdf']);
        $this->artisan('documents:cleanup-files')->assertFailed();
        Storage::disk('documents')->assertExists($document->file_path);
        $this->assertDatabaseHas('document_file_cleanups', ['file_path' => $document->file_path, 'last_error' => 'file_still_referenced']);
        $this->assertDatabaseHas('document_file_cleanups', ['file_path' => '../outside.pdf', 'last_error' => 'unsafe_path']);
    }

    public function test_missing_file_cleanup_is_idempotent_and_limit_is_bounded(): void
    {
        Storage::fake('documents');
        DocumentFileCleanup::create(['file_path' => 'doctor-documents/missing.pdf']);
        $this->artisan('documents:cleanup-files', ['--limit' => 0])->assertExitCode(2);
        $this->assertDatabaseCount('document_file_cleanups', 1);
        $this->artisan('documents:cleanup-files')->assertSuccessful();
        $this->artisan('documents:cleanup-files')->assertSuccessful();
        $this->assertDatabaseCount('document_file_cleanups', 0);
    }

    public function test_missing_file_metadata_hides_storage_paths_and_downloads_are_private(): void
    {
        $document = $this->document();
        $this->getJson('/api/doctor/documents')->assertOk()->assertJsonPath('documents.0.file_available', true)->assertJsonMissingPath('documents.0.file_path');
        $this->get('/api/doctor/documents/'.$document->id.'/download')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        Storage::disk('documents')->delete($document->file_path);
        $this->getJson('/api/doctor/documents/'.$document->id)->assertOk()->assertJsonPath('document.file_available', false)->assertJsonMissingPath('document.file_path');
        $this->get('/api/doctor/documents/'.$document->id.'/download')->assertNotFound();
    }
}
