<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GeminiController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'question' => 'required|string|max:1000',
            'context' => 'required|array',
        ]);

        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            return response()->json(['error' => 'Gemini API key not configured'], 500);
        }

        $context = $request->input('context');
        $question = $request->input('question');

        $systemPrompt = $this->buildSystemPrompt($context);

        try {
            $response = Http::timeout(30)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}",
                [
                    'systemInstruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [['text' => $question]],
                        ],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 800,
                        'temperature' => 0.7,
                    ],
                ]
            );

            if ($response->failed()) {
                $error = $response->json('error.message') ?? 'Gemini API error';
                return response()->json(['error' => $error], $response->status());
            }

            $text = $response->json('candidates.0.content.parts.0.text') ?? 'No response generated.';

            return response()->json(['reply' => $text]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gemini API unreachable: ' . $e->getMessage()], 503);
        }
    }

    private function buildSystemPrompt(array $context): string
    {
        $user = $context['user'] ?? [];
        $ratings = $context['ratings'] ?? [];
        $ratingSummary = $context['ratingSummary'] ?? [];
        $recs = $context['recommendations'] ?? [];
        $plan = $context['plan'] ?? [];

        $userName = $user['name'] ?? 'Unknown';
        $fitnessLevel = $user['fitness_level'] ?? 'unknown';
        $totalRatings = $ratingSummary['total'] ?? 0;
        $avgRating = $ratingSummary['average_rating'] ?? 0;
        $byDifficulty = !empty($ratingSummary['by_difficulty']) ? json_encode($ratingSummary['by_difficulty']) : 'none';
        $byMuscle = !empty($ratingSummary['by_muscle_group']) ? json_encode($ratingSummary['by_muscle_group']) : 'none';

        $recSummary = '';
        if (!empty($recs)) {
            $recNames = array_map(fn($r) => ($r['exercise_name'] ?? '?') . ' (Lvl ' . ($r['difficulty_level'] ?? '?') . ', score: ' . round($r['hybrid_score'] ?? 0, 3) . ')', array_slice($recs, 0, 10));
            $recSummary = implode(', ', $recNames);
        }

        $planSummary = '';
        if (!empty($plan['schedule'])) {
            foreach ($plan['schedule'] as $day) {
                if (!($day['rest_day'] ?? false)) {
                    $planSummary .= ($day['day'] ?? '') . ': ' . ($day['exercise_count'] ?? 0) . ' exercises. ';
                }
            }
        }

        $ratingsList = '';
        if (!empty($ratings)) {
            $items = array_map(fn($r) => ($r['exercise_name'] ?? '?') . ' (Lvl ' . ($r['difficulty_level'] ?? '?') . ', rated: ' . ($r['rating_value'] ?? '?') . ')', array_slice($ratings, 0, 20));
            $ratingsList = implode(', ', $items);
        }

        return <<<PROMPT
You are an AI assistant for the FitNEase fitness recommendation system console. You help testers and developers understand how the ML recommendation engine works for specific users.

SYSTEM ARCHITECTURE:
- Hybrid Recommender: combines Content-Based filtering (Cosine Similarity / TF-IDF) and Collaborative Filtering (SVD)
- Dynamic Netflix-style weighting: new users get more CB weight, users with more ratings get more CF weight
- Formula: cf_weight = min(0.45, rating_count * 0.03), cb_weight = 1.0 - cf_weight
- Content-Based scores exercises by similarity to what the user has rated (muscle group, difficulty, equipment)
- Collaborative Filtering finds patterns from similar users' ratings
- Fitness level filtering: Beginner=level 1 only, Intermediate=level 2 + 2-5 progressive level 3, Advanced=level 2-3 with level 3 prioritized
- Progressive difficulty mixing: intermediate users always get 2-5 level 3 exercises, slots increase as they rate level 3 exercises higher

CURRENT USER CONTEXT:
- Name: {$userName}
- Fitness Level: {$fitnessLevel}
- Total Ratings: {$totalRatings}, Average Rating: {$avgRating}
- Ratings by Difficulty: {$byDifficulty}
- Ratings by Muscle Group: {$byMuscle}
- Rated Exercises: {$ratingsList}
- Current Recommendations: {$recSummary}
- Weekly Plan: {$planSummary}

GUIDELINES:
- Give concise, specific answers about THIS user's data
- Explain ML behavior in simple terms
- When asked "why", reference the actual scores, weights, and rating patterns
- Keep responses under 200 words unless more detail is needed
- Do not make up data — only reference what is provided above
PROMPT;
    }
}
