<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\AdminController;

Route::middleware(['throttle:60,1'])->group(function () {
    // ==================== Public Routes (No Token Needed) ====================
    Route::post('/register', [AuthController::class, 'register']);
    
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/login',    [AuthController::class, 'login']);
        Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/auth/reset-password',  [AuthController::class, 'resetPassword']);
        Route::post('/auth/verify-email',    [AuthController::class, 'verifyEmail']);
    });

    Route::get('/config/destinations', [AuthController::class, 'getDestinations']);
    Route::get('/config/companies',    [AuthController::class, 'getCompanies']);

    // ==================== Protected Routes (Require Sanctum Token) ====================
    Route::middleware('auth:sanctum')->group(function () {

        // --- User Base ---
        Route::get('/user',   [AuthController::class, 'user']);
        Route::post('/user/submit-phone', [AuthController::class, 'submitPhone']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // --- Companions ---
        Route::get('/user/companions',          [AuthController::class, 'getCompanionDevices']);
        Route::post('/user/companions',         [AuthController::class, 'addCompanionDevice']);
        Route::delete('/user/companions/{id}',  [AuthController::class, 'removeCompanionDevice']);

        // ==================== Requires Phone Confirmation ====================
        Route::middleware('phone.confirmed')->group(function () {
            // --- Profile ---
            Route::put('/user/profile', [AuthController::class, 'updateProfile']);
            Route::post('/user/change-password', [AuthController::class, 'changePassword']);
            Route::post('/user/update-image', [AuthController::class, 'updateImage']);
            Route::post('/user/fcm-token', [AuthController::class, 'updateFcmToken']);
            Route::get('/user/balance-history', [TransferController::class, 'balanceHistory']);

            // --- Transfers ---
            Route::post('/transfers/send',            [TransferController::class, 'send']);
            Route::post('/transfers/search',          [TransferController::class, 'search']);
            Route::post('/transfers/pay',             [TransferController::class, 'pay']);
            Route::get('/transfers/report/sent',      [TransferController::class, 'sentReport']);
            Route::get('/transfers/report/received',  [TransferController::class, 'receivedReport']);
            Route::get('/transfers/stats',            [TransferController::class, 'stats']);
            Route::get('/transfers/{id}',             [TransferController::class, 'show']);
            Route::post('/transfers/{id}/cancel',     [TransferController::class, 'cancel']);

            // --- Admin ---
            Route::middleware('admin')->group(function () {
                Route::post('/admin/users',              [AdminController::class, 'createUser']);
                Route::get('/admin/users',               [AdminController::class, 'getAllUsers']);
                Route::put('/admin/users/{id}/balance',  [AdminController::class, 'updateBalance']);
                Route::delete('/admin/users/{id}',       [AdminController::class, 'deleteUser']);
                Route::get('/admin/audit-logs',          [AdminController::class, 'auditLogs']);
                Route::get('/admin/dashboard',           [AdminController::class, 'dashboard']);
                Route::get('/admin/pending-phones',      [AdminController::class, 'pendingPhoneConfirmations']);
                Route::post('/admin/users/{id}/confirm-phone', [AdminController::class, 'confirmPhone']);
            });
        });
    });
});
