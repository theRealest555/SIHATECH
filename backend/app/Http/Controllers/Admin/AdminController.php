<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Avis;
use App\Models\Payment;
use App\Models\Rendezvous;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'total_doctors' => User::where('role', 'medecin')->count(),
            'total_patients' => User::where('role', 'patient')->count(),
            'pending_reviews' => Avis::where('status', 'pending')->count(),
            'total_appointments' => Rendezvous::count(),
            'total_revenue' => Payment::where('status', 'completed')->sum('amount'),
            'recent_registrations' => User::orderBy('created_at', 'desc')->limit(5)->get(),
            'pending_doctor_verifications' => User::where('role', 'medecin')
                ->whereHas('doctor', function ($query) {
                    $query->where('is_verified', false);
                })
                ->count(),
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
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(15);

        return response()->json($users);
    }

    public function updateUserStatus(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:actif,inactif,en_attente',
        ]);

        $user->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Statut utilisateur mis à jour',
            'user' => $user,
        ]);
    }

    public function exportUserData(Request $request)
    {
        $format = $request->get('format', 'csv');
        $users = User::with(['userSubscriptions', 'payments'])->get();

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="users_export.csv"',
            ];

            $callback = function () use ($users) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'Nom', 'Prénom', 'Email', 'Rôle', 'Statut', 'Date d\'inscription']);

                foreach ($users as $user) {
                    fputcsv($file, [
                        $user->id,
                        $user->nom,
                        $user->prenom,
                        $user->email,
                        $user->role,
                        $user->status,
                        $user->created_at->format('Y-m-d H:i:s'),
                    ]);
                }
                fclose($file);
            };

            return Response::stream($callback, 200, $headers);
        }

        // Current implementation does not support actual Excel export.
        // For Excel format, return JSON response indicating export is being processed or that it's not supported
        return response()->json([
            'message' => 'Excel export is not yet implemented. Please use CSV format.',
            'requested_format' => $format,
        ], 501); // 501 Not Implemented
    }
}
