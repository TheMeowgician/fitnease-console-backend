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

    private const LEVEL_RANK = [
        'beginner' => 1,
        'intermediate' => 2,
        'advanced' => 3,
    ];

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
        $newLevel = $request->fitness_level;

        if ($oldLevel === $newLevel) {
            return response()->json([
                'message' => 'User is already at this fitness level.',
                'fitness_level' => $oldLevel,
            ]);
        }

        DB::connection('fitnease_auth')
            ->table('users')
            ->where('user_id', $id)
            ->update(['fitness_level' => $newLevel]);

        // Also update fitness_assessments so the mobile app sees the change
        // (mobile reads from assessment_data JSON, not users.fitness_level)
        $syncResult = $this->syncAssessmentFitnessLevel($id, $newLevel);

        AuditService::log('update_fitness_level', 'user', $id, [
            'old_level' => $oldLevel,
            'new_level' => $newLevel,
        ]);

        // Unlock achievement if this is an upgrade (not a downgrade)
        $achievementUnlocked = null;
        $oldRank = self::LEVEL_RANK[$oldLevel] ?? 0;
        $newRank = self::LEVEL_RANK[$newLevel] ?? 0;

        if ($newRank > $oldRank) {
            $achievementUnlocked = $this->tryUnlockLevelAchievement($id, $newLevel);
        }

        return response()->json([
            'message' => 'Fitness level updated.',
            'old_level' => $oldLevel,
            'new_level' => $newLevel,
            'achievement_unlocked' => $achievementUnlocked,
            'assessment_sync' => $syncResult,
        ]);
    }

    /**
     * Insert level achievement directly into engagement DB (if not already earned).
     * Uses try/catch so a failure here never blocks the fitness level update.
     */
    private function tryUnlockLevelAchievement(int $userId, string $level): ?array
    {
        try {
            // Find the level achievement
            $achievement = DB::connection('fitnease_engagement')
                ->table('achievements')
                ->where('achievement_type', 'special')
                ->whereRaw("JSON_EXTRACT(criteria_json, '$.type') = ?", ['level_progression'])
                ->whereRaw("JSON_EXTRACT(criteria_json, '$.level') = ?", [$level])
                ->first();

            if (!$achievement) {
                return null;
            }

            // Check if already unlocked
            $existing = DB::connection('fitnease_engagement')
                ->table('user_achievements')
                ->where('user_id', $userId)
                ->where('achievement_id', $achievement->achievement_id)
                ->first();

            if ($existing) {
                return ['name' => $achievement->achievement_name, 'already_earned' => true];
            }

            // Insert the achievement
            DB::connection('fitnease_engagement')
                ->table('user_achievements')
                ->insert([
                    'user_id' => $userId,
                    'achievement_id' => $achievement->achievement_id,
                    'progress_percentage' => 100.00,
                    'is_completed' => true,
                    'earned_at' => now(),
                    'points_earned' => $achievement->points_value,
                    'notification_sent' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            return ['name' => $achievement->achievement_name, 'points' => $achievement->points_value];
        } catch (\Exception $e) {
            // Never block fitness level update due to achievement failure
            return ['error' => 'Could not unlock achievement: ' . $e->getMessage()];
        }
    }

    /**
     * Update the fitness_level inside the initial_onboarding assessment's assessment_data JSON.
     * Uses JSON_SET to preserve all other fields in the JSON blob.
     * Wrapped in try/catch so failure never blocks the main update.
     */
    private function syncAssessmentFitnessLevel(int $userId, string $newLevel): ?string
    {
        try {
            $affected = DB::connection('fitnease_auth')
                ->update(
                    "UPDATE fitness_assessments SET assessment_data = JSON_SET(assessment_data, '$.fitness_level', ?), updated_at = NOW() WHERE user_id = ? AND assessment_type = ?",
                    [$newLevel, $userId, 'initial_onboarding']
                );
            return "synced:{$affected}";
        } catch (\Exception $e) {
            return "sync_error:" . $e->getMessage();
        }
    }
}
