<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\NotificationController;

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
            Route::get('/transfers/advanced-report',  [TransferController::class, 'advancedReport']);
            Route::get('/transfers/daily-limit',      [TransferController::class, 'dailyLimitStatus']);
            Route::get('/transfers/export/csv',       [TransferController::class, 'exportCsv']);
            Route::get('/transfers/{id}',             [TransferController::class, 'show']);
            Route::post('/transfers/{id}/cancel',     [TransferController::class, 'cancel']);
            Route::post('/transfers/{id}/notes',      [TransferController::class, 'addNote']);
            Route::get('/transfers/{id}/history',     [TransferController::class, 'transferHistory']);
            Route::post('/transfers/{id}/requests',   [TransferController::class, 'storeModificationRequest']);
            Route::get('/transfer-requests/my',       [TransferController::class, 'myModificationRequests']);

            // --- Supervisor ---
            Route::middleware('supervisor')->group(function () {
                Route::get('/supervisor/transfers/pending-approvals', [TransferController::class, 'pendingApprovals']);
                Route::post('/supervisor/transfers/{id}/approve',     [TransferController::class, 'approveTransfer']);
                Route::get('/supervisor/branches/{id}/stats',         [BranchController::class, 'stats']);
                Route::get('/supervisor/transfer-requests',           [TransferController::class, 'myModificationRequests']);
                Route::post('/supervisor/transfer-requests/{id}/review', [AdminController::class, 'reviewTransferRequest']);
            });

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
                // --- الفروع ---
                Route::get('/admin/branches',                    [BranchController::class, 'index']);
                Route::post('/admin/branches',                   [BranchController::class, 'store']);
                Route::put('/admin/branches/{id}',               [BranchController::class, 'update']);
                Route::delete('/admin/branches/{id}',            [BranchController::class, 'destroy']);
                Route::get('/admin/branches/{id}/stats',         [BranchController::class, 'stats']);
                Route::post('/admin/branches/{id}/transfer-user', [BranchController::class, 'transferUser']);
                // --- الحدود اليومية والأدوار ---
                Route::put('/admin/users/{id}/daily-limit',      [AdminController::class, 'updateDailyLimit']);
                Route::put('/admin/users/{id}/role',             [AdminController::class, 'updateRole']);
                // --- إحصائيات موسعة ---
                Route::get('/admin/dashboard/stats',             [AdminController::class, 'dashboardStats']);
                // --- الموافقات ---
                Route::get('/admin/transfers/pending-approvals', [TransferController::class, 'pendingApprovals']);
                Route::post('/admin/transfers/{id}/approve',     [TransferController::class, 'approveTransfer']);
                // --- النظام المالي والعمولات وأسعار الصرف ---
                Route::get('/admin/financial-settings',          [AdminController::class, 'financialSettings']);
                Route::post('/admin/commission-rules',           [AdminController::class, 'storeCommissionRule']);
                Route::delete('/admin/commission-rules/{id}',    [AdminController::class, 'deleteCommissionRule']);
                Route::post('/admin/exchange-rates',             [AdminController::class, 'upsertExchangeRate']);
                Route::put('/admin/branches/{id}/treasury',      [AdminController::class, 'updateBranchTreasury']);
                // --- إعدادات النظام والشركات والوجهات الديناميكية ---
                Route::get('/admin/system-settings',             [AdminController::class, 'systemSettings']);
                Route::post('/admin/system-settings',            [AdminController::class, 'saveSystemSettings']);
                Route::post('/admin/companies',                  [AdminController::class, 'storeCompany']);
                Route::post('/admin/companies/{id}/toggle',      [AdminController::class, 'toggleCompany']);
                Route::delete('/admin/companies/{id}',           [AdminController::class, 'deleteCompany']);
                Route::post('/admin/destinations',               [AdminController::class, 'storeDestination']);
                Route::post('/admin/destinations/{id}/toggle',   [AdminController::class, 'toggleDestination']);
                Route::delete('/admin/destinations/{id}',        [AdminController::class, 'deleteDestination']);
                // --- الرقابة والأمان والحظر وطلبات التعديل والاسترداد (الجزء 4) ---
                Route::get('/admin/security',                    [AdminController::class, 'securityOverview']);
                Route::post('/admin/security/unblock',           [AdminController::class, 'unblockIdentifier']);
                Route::post('/admin/users/{id}/revoke-sessions', [AdminController::class, 'revokeUserSessions']);
                Route::post('/admin/blacklist',                  [AdminController::class, 'storeBlacklistEntity']);
                Route::post('/admin/blacklist/{id}/toggle',      [AdminController::class, 'toggleBlacklistEntity']);
                Route::delete('/admin/blacklist/{id}',           [AdminController::class, 'deleteBlacklistEntity']);
                Route::post('/admin/transfer-requests/{id}/review', [AdminController::class, 'reviewTransferRequest']);
                // --- كشف الحساب والإقفال والجرد اليومي (الجزء 5) ---
                Route::get('/admin/statements',                  [AdminController::class, 'accountStatement']);
                Route::get('/admin/daily-closing',               [AdminController::class, 'dailyClosingReport']);
            });
        });

        // --- الإشعارات الداخلية ---
        Route::get('/notifications',              [NotificationController::class, 'index']);
        Route::post('/notifications/read-all',    [NotificationController::class, 'markAllAsRead']);
        Route::post('/notifications/{id}/read',   [NotificationController::class, 'markAsRead']);
        Route::delete('/notifications/{id}',      [NotificationController::class, 'destroy']);
    });
});
