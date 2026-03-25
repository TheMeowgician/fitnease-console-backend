<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $admin = AdminUser::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (!$admin->is_active) {
            return response()->json(['message' => 'Account is deactivated.'], 403);
        }

        $admin->update(['last_login_at' => now()]);

        $token = $admin->createToken('console-token')->plainTextToken;

        // Log after auth so Auth::id() won't work yet — pass manually
        AuditLog::create([
            'admin_user_id' => $admin->id,
            'action' => 'login',
            'resource_type' => 'auth',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'token' => $token,
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        AuditService::log('logout', 'auth');

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        $admin = $request->user();

        return response()->json([
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $admin->role,
            'last_login_at' => $admin->last_login_at,
        ]);
    }
}
