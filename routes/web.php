<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminWebController;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

// ==================== لوحة التحكم الإدارية الويب ====================
Route::prefix('admin-panel')->middleware(['web'])->group(function () {
    // صفحة الدخول للوحة الويب
    Route::get('/login', function () {
        return view('admin.login');
    })->name('admin.login');

    Route::post('/login', [AdminWebController::class, 'login'])->name('admin.login.post');
    Route::post('/logout', [AdminWebController::class, 'logout'])->name('admin.logout');

    // الصفحات والإجراءات المحمية
    Route::middleware(['admin.web'])->group(function () {
        Route::get('/', [AdminWebController::class, 'dashboard'])->name('admin.dashboard');

        // إدارة المستخدمين التفاعلية
        Route::get('/users', [AdminWebController::class, 'users'])->name('admin.users');
        Route::post('/users', [AdminWebController::class, 'createUser'])->name('admin.users.store');
        Route::post('/users/{id}/balance', [AdminWebController::class, 'adjustBalance'])->name('admin.users.balance');
        Route::put('/users/{id}/settings', [AdminWebController::class, 'updateUserSettings'])->name('admin.users.settings');
        Route::post('/users/{id}/toggle-active', [AdminWebController::class, 'toggleUserActive'])->name('admin.users.toggle');
        Route::post('/users/{id}/confirm-phone', [AdminWebController::class, 'confirmUserPhone'])->name('admin.users.phone');

        // إدارة الفروع
        Route::get('/branches', [AdminWebController::class, 'branches'])->name('admin.branches');
        Route::post('/branches', [AdminWebController::class, 'storeBranch'])->name('admin.branches.store');
        Route::put('/branches/{id}', [AdminWebController::class, 'updateBranch'])->name('admin.branches.update');
        Route::post('/branches/{id}/transfer-user', [AdminWebController::class, 'transferUserBranch'])->name('admin.branches.transfer-user');

        // الموافقات المعلقة
        Route::get('/approvals', [AdminWebController::class, 'approvals'])->name('admin.approvals');
        Route::post('/approvals/{id}', [AdminWebController::class, 'approveTransferWeb'])->name('admin.approvals.action');

        // إدارة الحوالات وتصدير CSV
        Route::get('/transfers', [AdminWebController::class, 'transfers'])->name('admin.transfers');
        Route::get('/transfers/export-csv', [AdminWebController::class, 'exportTransfersCsv'])->name('admin.transfers.export');
        Route::put('/transfers/{id}', [AdminWebController::class, 'updateTransferReceiver'])->name('admin.transfers.update');
        Route::post('/transfers/{id}/cancel', [AdminWebController::class, 'cancelTransferWeb'])->name('admin.transfers.cancel');

        // سجلات التدقيق
        Route::get('/audit-logs', [AdminWebController::class, 'auditLogs'])->name('admin.audit');

        // النظام المالي والعمولات وأسعار الصرف وخزائن الفروع
        Route::get('/financial-settings', [AdminWebController::class, 'financialSettings'])->name('admin.financial');
        Route::post('/commission-rules', [AdminWebController::class, 'storeCommissionRuleWeb'])->name('admin.commissions.store');
        Route::post('/commission-rules/{id}/toggle', [AdminWebController::class, 'toggleCommissionRuleWeb'])->name('admin.commissions.toggle');
        Route::delete('/commission-rules/{id}', [AdminWebController::class, 'deleteCommissionRuleWeb'])->name('admin.commissions.delete');
        Route::post('/exchange-rates', [AdminWebController::class, 'upsertExchangeRateWeb'])->name('admin.rates.upsert');
        Route::post('/branches/{id}/treasury', [AdminWebController::class, 'updateBranchTreasuryWeb'])->name('admin.branches.treasury');

        // إعدادات النظام والشركات والوجهات الديناميكية
        Route::get('/settings', [AdminWebController::class, 'systemSettings'])->name('admin.settings');
        Route::post('/settings', [AdminWebController::class, 'saveSystemSettingsWeb'])->name('admin.settings.save');
        Route::post('/companies', [AdminWebController::class, 'storeCompanyWeb'])->name('admin.companies.store');
        Route::post('/companies/{id}/toggle', [AdminWebController::class, 'toggleCompanyWeb'])->name('admin.companies.toggle');
        Route::delete('/companies/{id}', [AdminWebController::class, 'deleteCompanyWeb'])->name('admin.companies.delete');
        Route::post('/destinations', [AdminWebController::class, 'storeDestinationWeb'])->name('admin.destinations.store');
        Route::post('/destinations/{id}/toggle', [AdminWebController::class, 'toggleDestinationWeb'])->name('admin.destinations.toggle');
        Route::delete('/destinations/{id}', [AdminWebController::class, 'deleteDestinationWeb'])->name('admin.destinations.delete');

        // الرقابة والأمان والحظر وطلبات التعديل والاسترداد (الجزء 4)
        Route::get('/security', [AdminWebController::class, 'securityCenter'])->name('admin.security');
        Route::post('/security/unblock', [AdminWebController::class, 'unblockIdentifierWeb'])->name('admin.security.unblock');
        Route::post('/security/users/{id}/revoke-sessions', [AdminWebController::class, 'revokeUserSessionsWeb'])->name('admin.security.revoke');
        Route::post('/security/blacklist', [AdminWebController::class, 'storeBlacklistWeb'])->name('admin.blacklist.store');
        Route::post('/security/blacklist/{id}/toggle', [AdminWebController::class, 'toggleBlacklistWeb'])->name('admin.blacklist.toggle');
        Route::delete('/security/blacklist/{id}', [AdminWebController::class, 'deleteBlacklistWeb'])->name('admin.blacklist.delete');
        Route::post('/security/transfer-requests/{id}/review', [AdminWebController::class, 'reviewTransferRequestWeb'])->name('admin.transfer-requests.review');

        // كشوف الحساب، السندات القابلة للطباعة، والإقفال اليومي (الجزء 5)
        Route::get('/statements', [AdminWebController::class, 'statements'])->name('admin.statements');
        Route::get('/transfers/{id}/print', [AdminWebController::class, 'printTransferReceipt'])->name('admin.transfers.print');
        Route::get('/transactions/{id}/voucher', [AdminWebController::class, 'printTransactionVoucher'])->name('admin.transactions.voucher');
        Route::get('/daily-closing', [AdminWebController::class, 'dailyClosing'])->name('admin.daily-closing');
    });
});
