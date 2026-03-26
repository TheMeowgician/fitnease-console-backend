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
            $stats['active_users'] = DB::connection('fitnease_auth')
                ->table('personal_access_tokens')
                ->where('last_used_at', '>=', now()->subMinutes(15))
                ->distinct('tokenable_id')
                ->count('tokenable_id');
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
