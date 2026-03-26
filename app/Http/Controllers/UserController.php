<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::connection('fitnease_auth')->table('users');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($fitnessLevel = $request->query('fitness_level')) {
            $query->where('fitness_level', $fitnessLevel);
        }

        $users = $query->select([
            'user_id as id', 'username', 'first_name', 'last_name', 'email',
            'fitness_level', 'age', 'gender', 'created_at', 'updated_at',
        ])->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($users);
    }

    public function show(int $id): JsonResponse
    {
        $user = DB::connection('fitnease_auth')
            ->table('users')
            ->where('user_id', $id)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Get workout history from tracking DB
        $workouts = DB::connection('fitnease_tracking')
            ->table('workout_sessions')
            ->where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Get user's groups from social DB
        $groups = DB::connection('fitnease_social')
            ->table('group_members')
            ->join('groups', 'groups.group_id', '=', 'group_members.group_id')
            ->where('group_members.user_id', $id)
            ->select('groups.group_id as id', 'groups.group_name as name', 'group_members.member_role as role', 'group_members.joined_at')
            ->get();

        return response()->json([
            'user' => $user,
            'workouts' => $workouts,
            'groups' => $groups,
        ]);
    }

    public function updateFitnessLevel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'fitness_level' => 'required|string|in:beginner,intermediate,advanced',
        ]);

        $user = DB::connection('fitnease_auth')
            ->table('users')
            ->where('user_id', $id)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $oldLevel = $user->fitness_level;

        DB::connection('fitnease_auth')
            ->table('users')
            ->where('user_id', $id)
            ->update(['fitness_level' => $request->fitness_level]);

        AuditService::log('update_fitness_level', 'user', $id, [
            'old_level' => $oldLevel,
            'new_level' => $request->fitness_level,
        ]);

        return response()->json([
            'message' => 'Fitness level updated.',
            'old_level' => $oldLevel,
            'new_level' => $request->fitness_level,
        ]);
    }
}
