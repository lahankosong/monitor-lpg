<?php

use App\Http\Controllers\Api\GithubActionsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Tanpa CSRF, tanpa auth session
| Diakses oleh GitHub Actions scraper
|--------------------------------------------------------------------------
*/

Route::get('/health', [GithubActionsController::class, 'health']);

Route::prefix('github-actions')->group(function () {
    Route::get('/accounts', [GithubActionsController::class, 'getAccounts']);
    Route::post('/tokens',       [GithubActionsController::class, 'receiveTokens']);
    Route::post('/transactions', [GithubActionsController::class, 'receiveTransactions']);
});
