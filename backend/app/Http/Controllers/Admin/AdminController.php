<?php
// app/Http/Controllers/AdminController.php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Avis;
use App\Models\rendezvous;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'total_doctors' => User::where('role', 'doctor')->count(),
            'total_patients' => User::where('role', 'patient')->count(),
            'pending_reviews' => Avis::where('status', 'pending')->count(),
            'total_appointments' => rendezvous::count(),
            'total_revenue' => Payment::where('status', 'completed')->sum('amount'),
            'recent_registrations' => User::orderBy('created_at', 'desc')->limit(5)->get(),
            'pending_doctor_verifications' => User::where('role', 'doctor')
                ->where('verification_status', 'pending')
                ->count()
        ];

        return response()->json(['data' => $stats]);
    }

    public function getUsers(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(15);
        return response()->json($users);
    }

    public function updateUserStatus(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,suspended,banned'
        ]);

        $user->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Statut utilisateur mis à jour',
            'user' => $user
        ]);
    }

    public function getPendingReviews(): JsonResponse
    {
        $reviews = Avis::with(['patient', 'doctor', 'appointment'])
            ->where('status', 'pending')
            ->paginate(15);

        return response()->json($reviews);
    }

    public function moderateReview(Request $request, Avis $review): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'nullable|string|max:500'
        ]);

        $review->update([
            'status' => $request->action === 'approve' ? 'approved' : 'rejected',
            'moderated_at' => now(),
            'moderated_by' => auth()->id()
        ]);

        return response()->json([
            'message' => 'Avis modéré avec succès',
            'review' => $review->load(['patient', 'doctor'])
        ]);
    }

    public function exportUserData(Request $request)
    {
        $format = $request->get('format', 'excel');
        $users = User::with(['userSubscriptions', 'payments'])->get();

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="users_export.csv"'
            ];

            $callback = function() use ($users) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'Nom', 'Email', 'Rôle', 'Statut', 'Date d\'inscription']);
                
                foreach ($users as $user) {
                    fputcsv($file, [
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->role,
                        $user->status,
                        $user->created_at->format('Y-m-d H:i:s')
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        // Excel export logic here
        return response()->json(['message' => 'Export en cours...']);
    }
}