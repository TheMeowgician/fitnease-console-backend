<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $stats = [];

        try {
            // User stats from auth DB
            $stats['total_users'] = DB::connection('fitnease_auth')
                ->table('users')->count();

            $stats['users_by_fitness_level'] = DB::connection('fitnease_auth')
                ->table('users')
                ->selectRaw('fitness_level, COUNT(*) as count')
                ->groupBy('fitness_level')
                ->pluck('count', 'fitness_level');

            $stats['new_users_this_week'] = DB::connection('fitnease_auth')
                ->table('users')
                ->where('created_at', '>=', now()->subWeek())
                ->count();

            // Active users: tokens used in the last 15 minutes
            $activeTokens = DB::connection('fitnease_auth')
                ->table('personal_access_tokens')
                ->select('tokenable_id', DB::raw('MAX(last_used_at) as last_active'))
                ->where('last_used_at', '>=', now()->subMinutes(15))
                ->groupBy('tokenable_id')
                ->get();

            $stats['active_users'] = $activeTokens->count();

            if ($activeTokens->isNotEmpty()) {
                $activeIds = $activeTokens->pluck('tokenable_id')->toArray();
                $activeMap = $activeTokens->keyBy('tokenable_id');

                $users = DB::connection('fitnease_auth')
                    ->table('users')
                    ->whereIn('user_id', $activeIds)
                    ->select('user_id', 'username', 'first_name', 'last_name')
                    ->get();

                $stats['active_user_list'] = $users->map(fn ($u) => [
                    'id' => $u->user_id,
                    'name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->username,
                    'username' => $u->username,
                    'last_active' => $activeMap[$u->user_id]->last_active ?? null,
                ])->values();
            }
        } catch (\Exception $e) {
            $stats['auth_error'] = $e->getMessage();
        }

        try {
            // Workout stats from tracking DB
            $stats['total_workouts'] = DB::connection('fitnease_tracking')
                ->table('workout_sessions')->count();

            $stats['workouts_this_week'] = DB::connection('fitnease_tracking')
                ->table('workout_sessions')
                ->where('created_at', '>=', now()->subWeek())
                ->count();
        } catch (\Exception $e) {
            $stats['tracking_error'] = $e->getMessage();
        }

        try {
            // Group stats from social DB
            $stats['total_groups'] = DB::connection('fitnease_social')
                ->table('groups')->count();

            $stats['active_groups'] = DB::connection('fitnease_social')
                ->table('groups')
                ->where('is_active', true)
                ->count();
        } catch (\Exception $e) {
            $stats['social_error'] = $e->getMessage();
        }

        try {
            // Exercise count from content DB
            $stats['total_exercises'] = DB::connection('fitnease_content')
                ->table('exercises')->count();
        } catch (\Exception $e) {
            $stats['content_error'] = $e->getMessage();
        }

        // Connection checks for remaining services
        foreach ([
            'engagement' => 'fitnease_engagement',
            'planning' => 'fitnease_planning',
            'comms' => 'fitnease_comms',
            'media' => 'fitnease_media',
            'operations' => 'fitnease_operations',
        ] as $name => $connection) {
            try {
                DB::connection($connection)->getPdo();
            } catch (\Exception $e) {
                $stats["{$name}_error"] = $e->getMessage();
            }
        }

        // ML service HTTP health check
        try {
            $mlUrl = config('services.fitnease_ml.url');
            $response = Http::timeout(5)->get("{$mlUrl}/api/v1/model-status");
            if (!$response->successful()) {
                $stats['ml_error'] = 'ML service returned status ' . $response->status();
            }
        } catch (\Exception $e) {
            $stats['ml_error'] = $e->getMessage();
        }

        return response()->json($stats);
    }

    public function recentActivity(): JsonResponse
    {
        $activity = [];

        try {
            // Recent signups
            $activity['recent_users'] = DB::connection('fitnease_auth')
                ->table('users')
                ->select('user_id as id', 'username', 'first_name', 'last_name', 'email', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        } catch (\Exception $e) {
            $activity['auth_error'] = $e->getMessage();
        }

        try {
            // Recent workouts
            $activity['recent_workouts'] = DB::connection('fitnease_tracking')
                ->table('workout_sessions')
                ->select('session_id as id', 'user_id', 'session_type as type', 'is_completed as status', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        } catch (\Exception $e) {
            $activity['tracking_error'] = $e->getMessage();
        }

        return response()->json($activity);
    }
}
