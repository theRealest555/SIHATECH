<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\UploadDocumentRequest;
use App\Models\Doctor;
use App\Models\Document;
use App\Models\DocumentFileCleanup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Get all documents for the authenticated doctor
     */
    public function index(Request $request): JsonResponse
    {
        $doctor = $request->user()->doctor;
        $documents = $doctor->documents->map(fn (Document $document) => $this->present($document));

        return response()->json([
            'documents' => $documents,
        ]);
    }

    /**
     * Upload a new document
     */
    public function store(UploadDocumentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $doctor = $request->user()->doctor;

        // Store file
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $path = $file->store('doctor-documents', 'documents');

        abort_unless($path, 503, 'Document storage is unavailable.');
        try {
            $document = Document::create([
                'doctor_id' => $doctor->id,
                'type' => $validated['type'],
                'file_path' => $path,
                'original_name' => $originalName,
                'status' => 'pending',
            ]);
        } catch (\Throwable $error) {
            Storage::disk('documents')->delete($path);
            throw $error;
        }

        return response()->json([
            'message' => 'Document uploaded successfully',
            'document' => $this->present($document),
        ], 201);
    }

    /**
     * Get a specific document
     */
    public function show(string $id): JsonResponse
    {
        $doctor = Auth::user()->doctor;
        $document = Document::where('id', $id)
            ->where('doctor_id', $doctor->id)
            ->firstOrFail();

        return response()->json([
            'document' => $this->present($document),
        ]);
    }

    /**
     * Delete a document
     */
    public function destroy(string $id): JsonResponse
    {

        return DB::transaction(function () use ($id) {
            $doctor = Doctor::where('user_id', Auth::id())->lockForUpdate()->firstOrFail();
            $document = Document::where('id', $id)
                ->where('doctor_id', $doctor->id)
                ->lockForUpdate()->firstOrFail();

            // Only allow deletion if status is pending or rejected
            if ($document->status === 'approved') {
                return response()->json([
                    'message' => 'Cannot delete an approved document',
                ], 403);
            }

            // The cleanup request and record removal must commit together before touching storage.
            DocumentFileCleanup::firstOrCreate(['file_path' => $document->file_path]);
            $document->delete();

            return response()->json([
                'message' => 'Document deleted successfully',
                'file_cleanup_pending' => true,
            ]);
        });
    }

    public function download(Request $request, Document $document)
    {
        abort_unless($document->doctor_id === $request->user()->doctor?->id, 404);
        abort_unless(Storage::disk('documents')->exists($document->file_path), 404);

        return Storage::disk('documents')->download($document->file_path, $document->original_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function present(Document $document): Document
    {
        $document->setAttribute('file_available', Storage::disk('documents')->exists($document->file_path));

        return $document->makeHidden('file_path');
    }
}
