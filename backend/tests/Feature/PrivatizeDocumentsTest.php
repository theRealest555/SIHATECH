<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivatizeDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_preserves_files_and_apply_moves_verified_copies(): void
    {
        Storage::fake('public');
        Storage::fake('documents');
        $document = Document::factory()->create();
        Storage::disk('public')->put($document->file_path, 'credentials');
        $this->artisan('documents:privatize')->assertSuccessful();
        Storage::disk('public')->assertExists($document->file_path);
        Storage::disk('documents')->assertMissing($document->file_path);
        $this->artisan('documents:privatize', ['--apply' => true])->assertSuccessful();
        Storage::disk('public')->assertMissing($document->file_path);
        $this->assertSame('credentials', Storage::disk('documents')->get($document->file_path));
    }

    public function test_conflicting_private_copy_is_not_overwritten_or_deleted(): void
    {
        Storage::fake('public');
        Storage::fake('documents');
        $document = Document::factory()->create();
        Storage::disk('public')->put($document->file_path, 'original');
        Storage::disk('documents')->put($document->file_path, 'different');
        $this->artisan('documents:privatize', ['--apply' => true])->assertFailed();
        $this->assertSame('original', Storage::disk('public')->get($document->file_path));
        $this->assertSame('different', Storage::disk('documents')->get($document->file_path));
    }
}
