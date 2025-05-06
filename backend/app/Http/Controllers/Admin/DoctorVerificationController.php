<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Document;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DoctorVerificationController extends Controller
{
    /**
     * Get all doctors pending verification
     */
    public function pendingDoctors()
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
    public function pendingDocuments()
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
    public function showDocument($id)
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
    public function approveDocument(Request $request, $id)
    {
        $admin = $request->user();
        $document = Document::findOrFail($id);
        
        $document->update([
            'status' => 'approved',
            'user_id' => $admin->id,
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
    public function rejectDocument(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);
        
        $admin = $request->user();
        $document = Document::findOrFail($id);
        
        $document->update([
            'status' => 'rejected',
            'user_id' => $admin->id,
            'rejection_reason' => $request->rejection_reason,
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
    public function verifyDoctor(Request $request, $id)
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
    public function revokeVerification(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);
        
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
        ]);
        
        return response()->json([
            'message' => 'Doctor verification revoked successfully',
            'doctor' => $doctor->fresh()
        ]);
    }
}
