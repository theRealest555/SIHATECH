<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            if ($user->doctor) {
                $token = $user->createToken('doctor-api')->plainTextToken;
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'token' => $token,
                        'user' => $user,
                    ],
                ]);
            }
            Auth::logout();
            return response()->json([
                'status' => 'error',
                'message' => 'Seuls les médecins peuvent se connecter.'
            ], 403);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Identifiants incorrectes.'
        ], 401);
    }
}