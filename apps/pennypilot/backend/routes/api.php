<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check
Route::get('/health', fn() => response()->json(['status' => 'ok', 'app' => 'PennyPilot']));

// Auth routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);

    // Categories
    Route::apiResource('categories', CategoryController::class)->except(['show']);

    // Transactions
    Route::get('/transactions/summary', [TransactionController::class, 'summary']);
    Route::post('/transactions/bulk', [TransactionController::class, 'bulkStore']);
    Route::apiResource('transactions', TransactionController::class);

    // Accounts
    Route::get('/accounts', [AccountController::class, 'index']);
    Route::get('/accounts/default', [AccountController::class, 'getDefault']);
    Route::post('/accounts/set-balance', [AccountController::class, 'setBalance']);
    Route::put('/accounts/{id}', [AccountController::class, 'update']);
});
