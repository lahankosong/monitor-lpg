<?php

use App\Http\Controllers\Api\GithubActionsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes untuk integrasi external (GitHub Actions, dll)
| Prefix: /api
|
*/

// Health check
Route::get('/health', [GithubActionsController::class, 'health']);

// GitHub Actions endpoints
Route::prefix('github-actions')->group(function () {
    Route::post('/tokens', [GithubActionsController::class, 'receiveTokens']);
});
