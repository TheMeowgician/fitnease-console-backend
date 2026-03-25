<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MLController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/activity', [DashboardController::class, 'recentActivity']);

    // User Management
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}/fitness-level', [UserController::class, 'updateFitnessLevel']);

    // ML Recommendations
    Route::get('/ml/recommendations/{userId}', [MLController::class, 'getRecommendations']);
    Route::get('/ml/model-info', [MLController::class, 'getModelInfo']);

    // Audit Logs
    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    // Health Check
    Route::get('/health', [HealthController::class, 'index']);
});
