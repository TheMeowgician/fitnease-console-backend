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
        $query = DB::connection('fitnease_auth')
            ->table('users')
            ->leftJoinSub(
                DB::connection('fitnease_auth')
                    ->table('personal_access_tokens')
                    ->select('tokenable_id', DB::raw('MAX(last_used_at) as last_active'))
                    ->groupBy('tokenable_id'),
                'tokens',
                'users.user_id',
                '=',
                'tokens.tokenable_id'
            );

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('users.username', 'like', "%{$search}%")
                  ->orWhere('users.email', 'like', "%{$search}%")
                  ->orWhere('users.first_name', 'like', "%{$search}%")
                  ->orWhere('users.last_name', 'like', "%{$search}%");
            });
        }

        if ($fitnessLevel = $request->query('fitness_level')) {
            $query->where('users.fitness_level', $fitnessLevel);
        }

        $users = $query->select([
            'users.user_id as id', 'users.username', 'users.first_name', 'users.last_name', 'users.email',
            'users.fitness_level', 'users.age', 'users.gender', 'users.created_at', 'users.updated_at',
            'tokens.last_active',
            DB::raw('CASE WHEN tokens.last_active >= NOW() - INTERVAL 15 MINUTE THEN 1 ELSE 0 END as is_online'),
        ])->orderByDesc('is_online')->orderByDesc('users.created_at')->paginate(20);

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

    public function weeklyPlan(int $id): JsonResponse
    {
        try {
            $plan = DB::connection('fitnease_planning')
                ->table('weekly_workout_plans')
                ->where('user_id', $id)
                ->orderByDesc('is_current_week')
                ->orderByDesc('is_active')
                ->orderByDesc('week_start_date')
                ->first();

            if (!$plan) {
                return response()->json(['plan' => null]);
            }

            $planData = json_decode($plan->plan_data, true) ?? [];
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            $schedule = [];

            foreach ($days as $day) {
                $d = $planData[$day] ?? null;
                if (!$d) continue;
                $schedule[] = [
                    'day' => $day,
                    'rest_day' => $d['rest_day'] ?? false,
                    'planned' => $d['planned'] ?? false,
                    'completed' => $d['completed'] ?? false,
                    'exercise_count' => isset($d['exercises']) ? count($d['exercises']) : 0,
                    'estimated_duration' => $d['estimated_duration'] ?? null,
                    'focus_areas' => $d['focus_areas'] ?? [],
                ];
            }

            return response()->json([
                'plan' => [
                    'plan_id' => $plan->plan_id,
                    'week_start_date' => $plan->week_start_date,
                    'week_end_date' => $plan->week_end_date,
                    'is_active' => (bool) $plan->is_active,
                    'is_current_week' => (bool) $plan->is_current_week,
                    'total_workout_days' => $plan->total_workout_days,
                    'total_rest_days' => $plan->total_rest_days,
                    'total_exercises' => $plan->total_exercises,
                    'estimated_weekly_duration' => $plan->estimated_weekly_duration,
                    'completion_rate' => (float) $plan->completion_rate,
                    'generation_method' => $plan->generation_method,
                    'ml_generated' => (bool) $plan->ml_generated,
                    'schedule' => $schedule,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['plan' => null, 'error' => $e->getMessage()], 200);
        }
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
        $this->syncAssessmentFitnessLevel($id, $newLevel);

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
    private function syncAssessmentFitnessLevel(int $userId, string $newLevel): void
    {
        try {
            DB::connection('fitnease_auth')
                ->update(
                    "UPDATE fitness_assessments SET assessment_data = JSON_SET(assessment_data, '$.fitness_level', ?), updated_at = NOW() WHERE user_id = ? AND assessment_type = ?",
                    [$newLevel, $userId, 'initial_onboarding']
                );
        } catch (\Exception $e) {
            // Never block — the users table update already succeeded
        }
    }
}
