<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HealthController extends Controller
{
    private const SERVICES = [
        'auth' => ['connection' => 'fitnease_auth', 'port' => 8000],
        'content' => ['connection' => 'fitnease_content', 'port' => 8002],
        'tracking' => ['connection' => 'fitnease_tracking', 'port' => 8007],
        'engagement' => ['connection' => 'fitnease_engagement', 'port' => 8003],
        'social' => ['connection' => 'fitnease_social', 'port' => 8006],
        'planning' => ['connection' => 'fitnease_planning', 'port' => 8005],
        'comms' => ['connection' => 'fitnease_comms', 'port' => 8001],
        'media' => ['connection' => 'fitnease_media', 'port' => 8004],
        'operations' => ['connection' => 'fitnease_operations', 'port' => 8010],
    ];

    public function index(): JsonResponse
    {
        $health = [];
        $gatewayHost = 'http://18.136.99.170:8090';

        // Check each service DB connection
        foreach (self::SERVICES as $name => $config) {
            try {
                DB::connection($config['connection'])->getPdo();
                $health[$name] = [
                    'database' => 'connected',
                    'status' => 'healthy',
                ];
            } catch (\Exception $e) {
                $health[$name] = [
                    'database' => 'disconnected',
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Check ML service
        try {
            $mlUrl = config('services.fitnease_ml.url');
            $response = Http::timeout(5)->get("{$mlUrl}/api/v1/model-status");
            $health['ml'] = [
                'api' => $response->successful() ? 'reachable' : 'error',
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
            ];
        } catch (\Exception $e) {
            $health['ml'] = [
                'api' => 'unreachable',
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }

        // Check API gateway
        try {
            $response = Http::timeout(5)->get("{$gatewayHost}/auth/api/health");
            $health['gateway'] = [
                'api' => $response->successful() ? 'reachable' : 'error',
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
            ];
        } catch (\Exception $e) {
            $health['gateway'] = [
                'api' => 'unreachable',
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }

        $allHealthy = collect($health)->every(fn ($s) => $s['status'] === 'healthy');

        return response()->json([
            'overall' => $allHealthy ? 'healthy' : 'degraded',
            'services' => $health,
            'checked_at' => now()->toISOString(),
        ]);
    }
}
