<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MLController extends Controller
{
    public function getRecommendations(Request $request, int $userId): JsonResponse
    {
        $mlUrl = config('services.fitnease_ml.url');

        try {
            $query = [];
            if ($request->has('content_weight')) {
                $query['content_weight'] = (float) $request->query('content_weight');
            }
            if ($request->has('collaborative_weight')) {
                $query['collaborative_weight'] = (float) $request->query('collaborative_weight');
            }

            $response = Http::timeout(30)
                ->get("{$mlUrl}/api/v1/recommendations/{$userId}", $query);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'ML service returned an error.',
                    'status' => $response->status(),
                    'error' => $response->json(),
                ], $response->status());
            }

            AuditService::log('view_recommendations', 'ml', $userId);

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'ML service unreachable.',
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    public function getModelInfo(): JsonResponse
    {
        $mlUrl = config('services.fitnease_ml.url');

        try {
            $response = Http::timeout(10)
                ->get("{$mlUrl}/api/v1/model-status");

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Could not fetch model info.',
                ], $response->status());
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'ML service unreachable.',
                'error' => $e->getMessage(),
            ], 503);
        }
    }
}
