<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Admin as AdminModel;
use App\Models\AuditLog;
use App\Http\Requests\Admin\StoreAdminRequest;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Http\Requests\Admin\UpdateAdminStatusRequest;
use App\Http\Requests\Admin\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->checkAdminPermission($request)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%$search%")
                  ->orWhere('prenom', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        return response()->json($query->paginate(10));
    }

    /**
     * Store a newly created admin user.
     */
    public function storeAdmin(StoreAdminRequest $request): JsonResponse
    {
        if (!$this->checkAdminPermission($request)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $validated = $request->validated();

        try {
            $user = User::create([
                'nom' => $validated['nom'],
                'prenom' => $validated['prenom'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'telephone' => $validated['telephone'] ?? null,
                'role' => 'admin',
                'status' => 'actif',
            ]);

            $newAdmin = AdminModel::create([
                'user_id' => $user->id,
                'admin_status' => 1,
            ]);

            $admin = $request->user();

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'created_admin',
                'target_type' => 'user',
                'target_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Admin user created successfully',
                'user' => $user,
                'admin' => $newAdmin,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Admin creation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Admin user creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an admin's status.
     */
    public function updateAdminStatus(UpdateAdminStatusRequest $request, int $id): JsonResponse
    {
        if (!$this->checkAdminPermission($request)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $validated = $request->validated();

        try {
            $admin = AdminModel::findOrFail($id);
            $admin->update(['admin_status' => $validated['admin_status']]);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'updated_admin_status',
                'target_type' => 'admin',
                'target_id' => $id,
            ]);

            return response()->json([
                'message' => 'Admin status updated successfully',
                'admin' => $admin,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update admin status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified user.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        if (!$this->checkAdminPermission($request)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        try {
            $user = User::findOrFail($id);
            $data = ['user' => $user];

            switch ($user->role) {
                case 'patient':
                    $data['patient'] = Patient::where('user_id', $user->id)
                        ->with('medecinFavori.user')
                        ->first();
                    break;
                case 'medecin':
                    $data['doctor'] = Doctor::where('user_id', $user->id)
                        ->with(['speciality', 'documents'])
                        ->first();
                    break;
                case 'admin':
                    $data['admin'] = AdminModel::where('user_id', $user->id)
                        ->first();
                    break;
            }

            return response()->json($data);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'User not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified user's status.
     */
    public function updateStatus(UpdateUserStatusRequest $request, int $id): JsonResponse
    {
        if (!$this->checkAdminPermission($request)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $validated = $request->validated();

        try {
            $user = User::findOrFail($id);
            $user->update(['status' => $validated['status']]);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'updated_user_status',
                'target_type' => 'user',
                'target_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'User status updated successfully',
                'user' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update user status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset user password.
     */
    public function resetPassword(ResetPasswordRequest $request, int $id): JsonResponse
    {
        if (!$this->checkAdminPermission($request)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $validated = $request->validated();

        try {
            $user = User::findOrFail($id);
            $user->update(['password' => Hash::make($validated['password'])]);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'reset_user_password',
                'target_type' => 'user',
                'target_id' => $user->id,
            ]);

            return response()->json(['message' => 'Password reset successfully']);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reset password',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete the specified user.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$this->checkAdminPermission($request)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        try {
            $user = User::findOrFail($id);
            $userId = $user->id;

            $user->delete();

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'deleted_user',
                'target_type' => 'user',
                'target_id' => $userId,
            ]);

            return response()->json(['message' => 'User deleted successfully']);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if the user is an active admin.
     */
    private function checkAdminPermission(Request $request): bool
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return false;
        }

        $admin = AdminModel::where('user_id', $user->id)->first();
        return $admin && $admin->admin_status == 1;
    }
}
