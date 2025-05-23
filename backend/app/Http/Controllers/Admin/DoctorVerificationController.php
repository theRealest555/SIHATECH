<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Document;
use App\Models\AuditLog;
use App\Models\User;
use App\Http\Requests\Admin\RejectDocumentRequest;
use App\Http\Requests\Admin\RevokeVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DoctorVerificationController extends Controller
{
    /**
     * Get all doctors pending verification
     */
    public function pendingDoctors(): JsonResponse
    {
        $doctors = Doctor::with(['user', 'speciality', 'documents'])
                        ->where('is_verified', false)
                        ->get();

        return response()->json([
            'doctors' => $doctors
        ]);
    }

    /**
     * Get all documents pending verification
     */
    public function pendingDocuments(): JsonResponse
    {
        $documents = Document::with(['doctor.user', 'doctor.speciality'])
                            ->where('status', 'pending')
                            ->get();

        return response()->json([
            'documents' => $documents
        ]);
    }

    /**
     * View a specific document
     */
    public function showDocument(int $id): JsonResponse
    {
        $document = Document::with(['doctor.user', 'doctor.speciality'])
                           ->findOrFail($id);

        return response()->json([
            'document' => $document
        ]);
    }

    /**
     * Approve a document
     */
    public function approveDocument(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $document = Document::findOrFail($id);

        $document->update([
            'status' => 'approved',
            'admin_id' => $admin->id,
            'verified_at' => now(),
        ]);

        // Log this action
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'approved_document',
            'target_type' => 'document',
            'target_id' => $document->id,
        ]);

        return response()->json([
            'message' => 'Document approved successfully',
            'document' => $document->fresh()
        ]);
    }

    /**
     * Reject a document
     */
    public function rejectDocument(RejectDocumentRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();
        $admin = $request->user();
        $document = Document::findOrFail($id);

        $document->update([
            'status' => 'rejected',
            'admin_id' => $admin->id,
            'rejection_reason' => $validated['rejection_reason'],
            'verified_at' => now(),
        ]);

        // Log this action
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'rejected_document',
            'target_type' => 'document',
            'target_id' => $document->id,
        ]);

        return response()->json([
            'message' => 'Document rejected successfully',
            'document' => $document->fresh()
        ]);
    }

    /**
     * Verify a doctor
     */
    public function verifyDoctor(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $doctor = Doctor::findOrFail($id);

        // Check if doctor has at least one approved document
        $hasApprovedDocs = $doctor->documents()
                                ->where('status', 'approved')
                                ->exists();

        if (!$hasApprovedDocs) {
            return response()->json([
                'message' => 'Doctor must have at least one approved document to be verified'
            ], 400);
        }

        $doctor->update([
            'is_verified' => true
        ]);

        // Log this action
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'verified_doctor',
            'target_type' => 'doctor',
            'target_id' => $doctor->id,
        ]);

        return response()->json([
            'message' => 'Doctor verified successfully',
            'doctor' => $doctor->fresh()
        ]);
    }

    /**
     * Revoke doctor verification
     */
    public function revokeVerification(RevokeVerificationRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $admin = $request->user();
        $doctor = Doctor::findOrFail($id);

        $doctor->update([
            'is_verified' => false
        ]);

        // Log this action
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'revoked_doctor_verification',
            'target_type' => 'doctor',
            'target_id' => $doctor->id,
            'metadata' => json_encode(['reason' => $validated['reason']])
        ]);

        return response()->json([
            'message' => 'Doctor verification revoked successfully',
            'doctor' => $doctor->fresh()
        ]);
    }
}
