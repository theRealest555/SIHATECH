<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectDocumentRequest;
use App\Http\Requests\Admin\RevokeVerificationRequest;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Document;
use App\Services\DoctorCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DoctorVerificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['status' => 'sometimes|in:pending,verified,all', 'search' => 'nullable|string|max:120', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100']);
        $query = Doctor::with(['user:id,nom,prenom,email,status,email_verified_at', 'speciality:id,nom'])
            ->withCount(['documents as pending_documents_count' => fn ($q) => $q->where('status', 'pending')]);
        $status = $filters['status'] ?? 'pending';
        if ($status !== 'all') {
            $query->where('is_verified', $status === 'verified');
        }
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->whereHas('user', fn ($q) => $q->where(fn ($names) => $names->where('nom', 'like', '%'.$search.'%')->orWhere('prenom', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')));
        }
        $page = $query->orderBy('id')->paginate($filters['per_page'] ?? 15);

        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()]]);
    }

    public function show(Doctor $doctor, DoctorCredentials $credentials): JsonResponse
    {
        $doctor->load(['user:id,nom,prenom,email,status,email_verified_at', 'speciality:id,nom', 'documents']);
        $doctor->documents->each(function ($document) {
            $document->setAttribute('file_available', Storage::disk('documents')->exists($document->file_path));
            $document->makeHidden('file_path');
        });

        return response()->json(['data' => $doctor, 'meta' => ['required_document_types' => $credentials->requiredTypes(), 'missing_document_types' => $credentials->missingTypes($doctor)]]);
    }

    public function downloadDocument(Document $document)
    {
        abort_unless(Storage::disk('documents')->exists($document->file_path), 404);

        return Storage::disk('documents')->download($document->file_path, $document->original_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function pendingDoctors(): JsonResponse
    {
        return response()->json(['doctors' => Doctor::with(['user', 'speciality', 'documents'])->where('is_verified', false)->get()]);
    }

    public function pendingDocuments(): JsonResponse
    {
        return response()->json(['documents' => Document::with(['doctor.user', 'doctor.speciality'])->where('status', 'pending')->get()]);
    }

    public function showDocument(int $id): JsonResponse
    {
        return response()->json(['document' => Document::with(['doctor.user', 'doctor.speciality'])->findOrFail($id)]);
    }

    private function lockedDocument(int $id): array
    {
        $reference = Document::findOrFail($id);
        $doctor = Doctor::whereKey($reference->doctor_id)->lockForUpdate()->firstOrFail();
        $document = Document::whereKey($id)->where('doctor_id', $doctor->id)->lockForUpdate()->firstOrFail();

        return [$doctor, $document];
    }

    private function checkStatus(Request $request, Document $document): void
    {
        if ($request->filled('expected_status') && $request->input('expected_status') !== $document->status) {
            abort(409, 'This document was reviewed by another administrator. Refresh before deciding.');
        }
    }

    private function audit(Request $request, string $action, $target, array $metadata = []): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'target_type' => $target::class, 'target_id' => $target->id, 'metadata' => $metadata ? json_encode($metadata) : null]);
    }

    public function approveDocument(Request $request, int $id): JsonResponse
    {
        $request->validate(['expected_status' => 'sometimes|in:pending,approved,rejected']);

        return DB::transaction(function () use ($request, $id) {
            [, $document] = $this->lockedDocument($id);
            $this->checkStatus($request, $document);
            abort_unless(Storage::disk('documents')->exists($document->file_path), 409, 'The document file is missing. Ask the doctor to upload it again.');
            if ($document->status !== 'approved') {
                $document->update(['status' => 'approved', 'rejection_reason' => null, 'admin_id' => $request->user()->id, 'verified_at' => now()]);
                $this->audit($request, 'approved_document', $document);
            }

            return response()->json(['message' => 'Document approved successfully', 'document' => $document->fresh()]);
        });
    }

    public function rejectDocument(RejectDocumentRequest $request, int $id, DoctorCredentials $credentials): JsonResponse
    {
        return DB::transaction(function () use ($request, $id, $credentials) {
            [$doctor, $document] = $this->lockedDocument($id);
            $this->checkStatus($request, $document);
            $reason = $request->validated()['rejection_reason'];
            $document->update(['status' => 'rejected', 'admin_id' => $request->user()->id, 'rejection_reason' => $reason, 'verified_at' => now()]);
            $this->audit($request, 'rejected_document', $document, ['reason' => $reason]);
            if ($doctor->is_verified && $credentials->missingTypes($doctor)) {
                $doctor->update(['is_verified' => false]);
                $this->audit($request, 'revoked_doctor_verification', $doctor, ['reason' => 'Required credential rejected: '.$reason, 'document_id' => $document->id]);
            }

            return response()->json(['message' => 'Document rejected successfully', 'document' => $document->fresh(), 'doctor_verified' => (bool) $doctor->is_verified]);
        });
    }

    public function verifyDoctor(Request $request, int $id, DoctorCredentials $credentials): JsonResponse
    {
        return DB::transaction(function () use ($request, $id, $credentials) {
            $doctor = Doctor::whereKey($id)->lockForUpdate()->firstOrFail();
            $missing = $credentials->missingTypes($doctor);
            if ($missing) {
                return response()->json(['message' => 'Approved credential files are required before verification.', 'missing_document_types' => $missing], 400);
            }
            if (! $doctor->user->email_verified_at || $doctor->user->status !== 'actif' || ! $doctor->speciality_id) {
                return response()->json(['message' => 'The doctor needs a verified email, active account and speciality before verification.'], 409);
            }
            if (! $doctor->is_verified) {
                $doctor->update(['is_verified' => true]);
                $this->audit($request, 'verified_doctor', $doctor);
            }

            return response()->json(['message' => 'Doctor verified successfully', 'doctor' => $doctor->fresh()]);
        });
    }

    public function revokeVerification(RevokeVerificationRequest $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $doctor = Doctor::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($doctor->is_verified) {
                $doctor->update(['is_verified' => false]);
                $this->audit($request, 'revoked_doctor_verification', $doctor, ['reason' => $request->validated()['reason']]);
            }

            return response()->json(['message' => 'Doctor verification revoked successfully', 'doctor' => $doctor->fresh()]);
        });
    }
}
