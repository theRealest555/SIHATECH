<?php

namespace Tests\Feature\Doctor;

use App\Models\Doctor;
use App\Models\Document;
use App\Models\User; //
use Illuminate\Foundation\Testing\RefreshDatabase; //
use Illuminate\Http\UploadedFile; //
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctorUser; //

    protected Doctor $doctor; //

    protected function setUp(): void
    {
        parent::setUp();
        $this->doctorUser = User::factory()->create(['role' => 'medecin', 'email_verified_at' => now(), 'status' => 'actif']); //
        $this->doctor = Doctor::factory()->create(['user_id' => $this->doctorUser->id]); //
        Sanctum::actingAs($this->doctorUser, ['role:medecin']);
        Storage::fake('documents');
    }

    public function test_doctor_can_list_their_documents()
    {
        Document::factory(3)->create(['doctor_id' => $this->doctor->id]); //

        $response = $this->getJson('/api/doctor/documents'); //

        $response->assertStatus(200)
            ->assertJsonCount(3, 'documents');
    }

    public function test_doctor_can_upload_a_document()
    {
        $file = UploadedFile::fake()->create('diploma.pdf', 1000, 'application/pdf');
        $data = [
            'file' => $file,
            'type' => 'diplome',
        ];

        $response = $this->postJson('/api/doctor/documents', $data); //

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Document uploaded successfully')
            ->assertJsonPath('document.type', 'diplome')
            ->assertJsonPath('document.original_name', 'diploma.pdf')
            ->assertJsonPath('document.status', 'pending');

        $document = Document::first(); //
        $this->assertTrue(Storage::disk('documents')->exists($document->file_path));
        $this->assertDatabaseHas('documents', ['original_name' => 'diploma.pdf', 'doctor_id' => $this->doctor->id]); //
    }

    public function test_doctor_upload_document_fails_with_invalid_data()
    {
        $response = $this->postJson('/api/doctor/documents', ['type' => 'invalid_type']); //

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file', 'type']);
    }

    public function test_doctor_can_show_their_specific_document()
    {
        $document = Document::factory()->create(['doctor_id' => $this->doctor->id]); //

        $response = $this->getJson('/api/doctor/documents/'.$document->id); //

        $response->assertStatus(200)
            ->assertJsonPath('document.id', $document->id);
    }

    public function test_doctor_cannot_show_document_of_another_doctor()
    {
        $otherDoctor = Doctor::factory()->create(); //
        $document = Document::factory()->create(['doctor_id' => $otherDoctor->id]); //

        $response = $this->getJson('/api/doctor/documents/'.$document->id); //
        $response->assertStatus(404); // Or 403 if explicitly handled
    }

    public function test_doctor_can_delete_pending_document()
    {
        $document = Document::factory()->create([ //
            'doctor_id' => $this->doctor->id,
            'status' => 'pending',
            'file_path' => 'doctor-documents/test.pdf',
        ]);
        Storage::disk('documents')->put($document->file_path, 'content');

        $response = $this->deleteJson('/api/doctor/documents/'.$document->id); //

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Document deleted successfully');
        $this->assertDatabaseMissing('documents', ['id' => $document->id]); //
        $this->assertDatabaseHas('document_file_cleanups', ['file_path' => $document->file_path]);
        $this->assertTrue(Storage::disk('documents')->exists($document->file_path));
        $this->artisan('documents:cleanup-files')->assertSuccessful();
        $this->assertFalse(Storage::disk('documents')->exists($document->file_path));
    }

    public function test_doctor_cannot_delete_approved_document()
    {
        $document = Document::factory()->create([ //
            'doctor_id' => $this->doctor->id,
            'status' => 'approved',
        ]);
        $response = $this->deleteJson('/api/doctor/documents/'.$document->id); //

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Cannot delete an approved document');
        $this->assertDatabaseHas('documents', ['id' => $document->id]); //
    }

    public function test_patient_cannot_access_doctor_document_routes()
    {
        $patientUser = User::factory()->create(['role' => 'patient']); //
        Sanctum::actingAs($patientUser, ['role:patient']);

        $response = $this->getJson('/api/doctor/documents'); //
        $response->assertStatus(403);
    }

    public function test_executable_and_oversized_uploads_are_rejected(): void
    {
        foreach ([UploadedFile::fake()->create('script.php', 1, 'application/x-httpd-php'), UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')] as $file) {
            $this->postJson('/api/doctor/documents', ['file' => $file, 'type' => 'licence'])->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }
}
