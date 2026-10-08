<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Avis;
use App\Models\Doctor;
use App\Models\Rendezvous;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    private function present(Avis $review, bool $admin = false): array
    {
        $data = ['id' => $review->id, 'appointment_id' => $review->rendezvous_id, 'rating' => $review->rating, 'comment' => $review->comment, 'status' => $review->status, 'created_at' => $review->created_at?->toIso8601String(), 'moderated_at' => $review->moderated_at?->toIso8601String(), 'moderated_by' => $review->moderated_by, 'reason' => $review->moderation_reason];
        $data['doctor_name'] = $review->doctor ? trim($review->doctor->prenom.' '.$review->doctor->nom) : 'Doctor account unavailable';
        if ($admin) {
            $data['patient_name'] = $review->patient ? trim($review->patient->prenom.' '.$review->patient->nom) : 'Patient account unavailable';
        }

        return $data;
    }

    public function index(Request $request)
    {
        return $this->listing($request, false);
    }

    public function pending(Request $request)
    {
        return $this->listing($request, true, 'pending');
    }

    public function adminIndex(Request $request)
    {
        return $this->listing($request, true);
    }

    private function listing(Request $request, bool $admin, ?string $fixedStatus = null)
    {
        $filters = $request->validate(['status' => 'nullable|in:pending,approved,rejected', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100']);
        $query = Avis::with(['doctor:id,nom,prenom', 'patient:id,nom,prenom']);
        if (! $admin) {
            $query->where('patient_id', $request->user()->id);
        }
        $status = $fixedStatus ?? ($filters['status'] ?? null);
        if ($status) {
            $query->where('status', $status);
        }
        $page = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($filters['per_page'] ?? 15);

        return response()->json(['data' => $page->getCollection()->map(fn ($review) => $this->present($review, $admin)), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function context(Request $request, int $appointment)
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 404, 'Patient profile not found.');
        $visit = Rendezvous::whereKey($appointment)->where('patient_id', $patient->id)->firstOrFail();
        $doctor = $visit->doctor()->withTrashed()->with('user')->first();
        $review = Avis::where('rendezvous_id', $visit->id)->where('patient_id', $request->user()->id)->with('doctor')->first();

        return response()->json(['data' => ['appointment_id' => $visit->id, 'doctor_name' => $doctor?->full_name ?? 'Doctor unavailable', 'starts_at' => $visit->date_heure->toIso8601String(), 'statut' => $visit->statut, 'can_submit' => ! $review && $visit->statut === 'terminé' && $visit->date_heure->lte(now()), 'review' => $review ? $this->present($review) : null]], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function store(Request $request, int $appointment)
    {
        $data = $request->validate(['rating' => 'required|integer|min:1|max:5', 'comment' => 'nullable|string|max:2000']);
        $patient = $request->user()->patient;
        abort_unless($patient, 404, 'Patient profile not found.');
        $visit = Rendezvous::whereKey($appointment)->where('patient_id', $patient->id)->firstOrFail();
        $review = DB::transaction(function () use ($request, $data, $visit, $patient) {
            $doctor = Doctor::withTrashed()->whereKey($visit->doctor_id)->lockForUpdate()->firstOrFail();
            $visit = Rendezvous::whereKey($visit->id)->where('patient_id', $patient->id)->lockForUpdate()->firstOrFail();
            abort_unless($visit->statut === 'terminé' && $visit->date_heure->lte(now()), 409, 'Only completed past appointments can be reviewed.');
            abort_if(Avis::where('rendezvous_id', $visit->id)->exists(), 409, 'A review has already been submitted for this appointment.');

            return Avis::create(['patient_id' => $request->user()->id, 'doctor_id' => $doctor->user_id, 'rendezvous_id' => $visit->id, 'rating' => $data['rating'], 'comment' => $data['comment'] ?? null, 'status' => 'pending']);
        }, 3);

        return response()->json(['message' => 'Review submitted for moderation.', 'review' => $this->present($review->load('doctor'))], 201);
    }

    public function moderate(Request $request, Avis $review)
    {
        $data = $request->validate(['action' => 'required|in:approve,reject', 'expected_status' => 'required|in:pending,approved,rejected', 'reason' => 'nullable|required_if:action,reject|string|max:500']);
        $result = DB::transaction(function () use ($request, $review, $data) {
            // Match appointment status updates: doctor, appointment, then review.
            $doctor = Doctor::withTrashed()->where('user_id', $review->doctor_id)->lockForUpdate()->first();
            $visit = $review->rendezvous_id ? Rendezvous::whereKey($review->rendezvous_id)->lockForUpdate()->first() : null;
            $current = Avis::whereKey($review->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->status === $data['expected_status'], 409, 'This review changed. Refresh before deciding again.');
            $status = $data['action'] === 'approve' ? 'approved' : 'rejected';
            if ($status === 'approved') {
                abort_unless($doctor && $visit && $visit->doctor_id === $doctor->id && $visit->patient?->user_id === $current->patient_id && $visit->statut === 'terminé' && $visit->date_heure->lte(now()) && $current->rating >= 1 && $current->rating <= 5, 409, 'This review lacks a valid completed appointment. Reconcile its history before approval.');
            }
            if ($current->status === $status) {
                return $current;
            }
            $current->update(['status' => $status, 'moderated_at' => now(), 'moderated_by' => $request->user()->id, 'moderation_reason' => $status === 'rejected' ? $data['reason'] : null]);
            if ($doctor) {
                $doctor->updateAverageRating();
            }
            AuditLog::create(['user_id' => $request->user()->id, 'action' => $status === 'approved' ? 'approved_review' : 'rejected_review', 'target_type' => Avis::class, 'target_id' => $current->id, 'metadata' => json_encode(['reason' => $data['reason'] ?? null])]);

            return $current;
        }, 3);

        return response()->json(['message' => 'Avis modéré avec succès', 'review' => $this->present($result->load('doctor'), true)]);
    }
}
