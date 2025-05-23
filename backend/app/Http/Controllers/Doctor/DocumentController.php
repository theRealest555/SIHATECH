<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Http\Requests\Doctor\UploadDocumentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class DocumentController extends Controller
{
    /**
     * Get all documents for the authenticated doctor
     */
    public function index(Request $request): JsonResponse
    {
        $doctor = $request->user()->doctor;
        $documents = $doctor->documents;

        return response()->json([
            'documents' => $documents
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
        $path = $file->store('doctor-documents', 'public');

        $document = Document::create([
            'doctor_id' => $doctor->id,
            'type' => $validated['type'],
            'file_path' => $path,
            'original_name' => $originalName,
            'status' => 'pending'
        ]);

        return response()->json([
            'message' => 'Document uploaded successfully',
            'document' => $document
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
            'document' => $document
        ]);
    }

    /**
     * Delete a document
     */
    public function destroy(string $id): JsonResponse
    {
        $doctor = Auth::user()->doctor;
        $document = Document::where('id', $id)
                           ->where('doctor_id', $doctor->id)
                           ->firstOrFail();

        // Only allow deletion if status is pending or rejected
        if ($document->status === 'approved') {
            return response()->json([
                'message' => 'Cannot delete an approved document'
            ], 403);
        }

        // Delete file from storage
        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'message' => 'Document deleted successfully'
        ]);
    }
}
