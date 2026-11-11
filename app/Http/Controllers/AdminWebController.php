<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Branch;
use App\Models\Transfer;
use App\Models\AuditLog;
use App\Models\CommissionRule;
use App\Models\ExchangeRate;
use App\Models\BalanceTransaction;
use App\Models\TransferHistoryLog;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * متحكم لوحة التحكم الإدارية الويب (تفاعلي كامل)
 * يستخدم جلسات الويب (web session) بدلاً من Sanctum
 */
class AdminWebController extends Controller
{
    // ==================== تسجيل الدخول الإداري ====================
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->where('role', 'Admin')->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة أو ليس لديك صلاحية مدير']);
        }

        // حفظ في الجلسة
        session(['admin_user_id' => $user->id, 'admin_username' => $user->username]);

        return redirect()->route('admin.dashboard');
    }

    // ==================== تسجيل الخروج ====================
    public function logout()
    {
        session()->forget(['admin_user_id', 'admin_username']);
        return redirect()->route('admin.login');
    }

    // ==================== الداشبورد ====================
    public function dashboard()
    {
        $data = [
            'totalUsers'           => User::count(),
            'totalTransfers'       => Transfer::count(),
            'pendingCount'         => Transfer::where('status', 'pending')->count(),
            'paidCount'            => Transfer::where('status', 'paid')->count(),
            'totalCommissions'     => (float) Transfer::whereNotIn('status', ['cancelled'])->sum('commission_amount'),
            'pendingApprovals'     => Transfer::where('approval_status', 'pending_approval')->count(),
            'pendingPhones'        => User::where('phone_status', 'pending')->whereNotNull('phone')->count(),
            'branchesCount'        => Branch::count(),
            'lowLiquidityBranches' => Branch::where('credit_limit', '>', 0)->whereColumn('balance', '<', 'credit_limit')->get(),
            'byCompany'            => Transfer::selectRaw('company_type, count(*) as count, sum(amount) as total, sum(commission_amount) as commissions')
                ->groupBy('company_type')->get(),
            'recentTransfers'      => Transfer::orderBy('created_at', 'desc')->take(10)->get(),
        ];
        return view('admin.dashboard', $data);
    }

    // ==================== إدارة المستخدمين ====================
    public function users(Request $request)
    {
        $search = $request->get('search', '');
        $roleFilter = $request->get('role', '');
        $branchFilter = $request->get('branch_id', '');

        $users = User::with('branch')
            ->when($search, fn($q) => $q->where(function ($sub) use ($search) {
                $sub->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('user_number', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->when($roleFilter, fn($q) => $q->where('role', $roleFilter))
            ->when($branchFilter, fn($q) => $q->where('branch_id', $branchFilter))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $branches = Branch::orderBy('name')->get();

        return view('admin.users', compact('users', 'search', 'roleFilter', 'branchFilter', 'branches'));
    }

    // ==================== إنشاء مستخدم جديد من الويب ====================
    public function createUser(Request $request)
    {
        $request->validate([
            'username'                   => 'required|string|min:3|max:100',
            'email'                      => 'required|email|unique:users,email',
            'phone'                      => 'nullable|string|max:20',
            'password'                   => 'required|string|min:6',
            'role'                       => 'required|in:Admin,Supervisor,User',
            'branch_id'                  => 'nullable|exists:branches,id',
            'balance'                    => 'nullable|numeric|min:0',
            'daily_transfer_limit'       => 'nullable|numeric|min:0',
            'daily_transfer_count_limit' => 'nullable|integer|min:0',
        ]);

        do {
            $userNumber = sprintf('%06d', mt_rand(100000, 999999));
        } while (User::where('user_number', $userNumber)->exists());

        $initialBalance = (float) ($request->balance ?? 0);

        $user = User::create([
            'username'                   => $request->username,
            'email'                      => $request->email,
            'phone'                      => $request->phone,
            'phone_status'               => $request->filled('phone') ? 'confirmed' : 'pending',
            'password'                   => Hash::make($request->password),
            'role'                       => $request->role,
            'branch_id'                  => $request->branch_id ?: null,
            'balance'                    => $initialBalance,
            'daily_transfer_limit'       => $request->daily_transfer_limit ?? 0,
            'daily_transfer_count_limit' => $request->daily_transfer_count_limit ?? 0,
            'user_number'                => $userNumber,
            'email_verified_at'          => now(),
            'is_active'                  => true,
        ]);

        if ($initialBalance > 0) {
            BalanceTransaction::create([
                'user_id'        => $user->id,
                'type'           => 'credit',
                'amount'         => $initialBalance,
                'description'    => 'رصيد افتتاحي عند إنشاء الحساب من الإدارة',
                'balance_before' => 0,
                'balance_after'  => $initialBalance,
            ]);
        }

        AuditService::log('user_created_web', $user);

        return back()->with('success', "تم إنشاء المستخدم ({$user->username}) بنجاح — رقم الدخول: {$userNumber}");
    }

    // ==================== شحن / خصم رصيد المستخدم ====================
    public function adjustBalance(Request $request, $id)
    {
        $request->validate([
            'operation'   => 'required|in:credit,debit,set',
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        $user = User::findOrFail($id);
        $balanceBefore = (float) $user->balance;
        $amount = (float) $request->amount;

        if ($request->operation === 'credit') {
            $balanceAfter = $balanceBefore + $amount;
            $txType = 'credit';
            $defaultDesc = 'إيداع / تغذية رصيد من الإدارة';
            $notifTitle = '💰 تم شحن رصيدك';
            $notifBody = "تم إيداع مبلغ {$amount} في حسابك. رصيدك الحالي: {$balanceAfter}";
        } elseif ($request->operation === 'debit') {
            if ($balanceBefore < $amount) {
                return back()->withErrors(['balance' => "رصيد المستخدم الحالي ({$balanceBefore}) أقل من المبلغ المراد خصمه ({$amount})"]);
            }
            $balanceAfter = $balanceBefore - $amount;
            $txType = 'debit';
            $defaultDesc = 'خصم / سحب رصيد من الإدارة';
            $notifTitle = '📉 تم خصم مبلغ من رصيدك';
            $notifBody = "تم خصم مبلغ {$amount} من حسابك. رصيدك الحالي: {$balanceAfter}";
        } else {
            $balanceAfter = $amount;
            $txType = $balanceAfter >= $balanceBefore ? 'credit' : 'debit';
            $amount = abs($balanceAfter - $balanceBefore);
            $defaultDesc = 'تعديل الرصيد المباشر من الإدارة';
            $notifTitle = '💳 تم تحديث رصيدك';
            $notifBody = "تم تحديث رصيد حسابك إلى: {$balanceAfter}";
        }

        $user->update(['balance' => $balanceAfter]);

        if ($amount > 0) {
            BalanceTransaction::create([
                'user_id'        => $user->id,
                'type'           => $txType,
                'amount'         => $amount,
                'description'    => $request->description ?: $defaultDesc,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
            ]);
        }

        NotificationController::sendToUser($user->id, $notifTitle, $notifBody, 'balance');

        AuditService::log('balance_adjusted_web', $user, ['balance' => $balanceBefore], [
            'balance'     => $balanceAfter,
            'operation'   => $request->operation,
            'description' => $request->description ?: $defaultDesc,
        ]);

        return back()->with('success', "تم تحديث رصيد المستخدم ({$user->username}) إلى " . number_format($balanceAfter, 2));
    }

    // ==================== تحديث إعدادات وصلاحيات المستخدم ====================
    public function updateUserSettings(Request $request, $id)
    {
        $request->validate([
            'username'                   => 'required|string|min:3|max:100',
            'phone'                      => 'nullable|string|max:20',
            'role'                       => 'required|in:Admin,Supervisor,User',
            'branch_id'                  => 'nullable|exists:branches,id',
            'daily_transfer_limit'       => 'required|numeric|min:0',
            'daily_transfer_count_limit' => 'required|integer|min:0',
            'phone_status'               => 'required|in:pending,confirmed,rejected',
            'is_active'                  => 'required|boolean',
        ]);

        $user = User::findOrFail($id);
        $oldValues = $user->only(['role', 'branch_id', 'daily_transfer_limit', 'daily_transfer_count_limit', 'phone_status', 'is_active']);

        $user->update([
            'username'                   => $request->username,
            'phone'                      => $request->phone,
            'role'                       => $request->role,
            'branch_id'                  => $request->branch_id ?: null,
            'daily_transfer_limit'       => $request->daily_transfer_limit,
            'daily_transfer_count_limit' => $request->daily_transfer_count_limit,
            'phone_status'               => $request->phone_status,
            'is_active'                  => (bool) $request->is_active,
        ]);

        AuditService::log('user_settings_updated_web', $user, $oldValues, $user->only(array_keys($oldValues)));

        return back()->with('success', "تم تحديث بيانات وصلاحيات المستخدم ({$user->username}) بنجاح");
    }

    // ==================== تفعيل / إيقاف حساب مستخدم ====================
    public function toggleUserActive($id)
    {
        $user = User::findOrFail($id);
        $newState = !$user->is_active;
        $user->update(['is_active' => $newState]);

        AuditService::log($newState ? 'user_activated_web' : 'user_deactivated_web', $user);

        return back()->with('success', $newState
            ? "تم تفعيل حساب ({$user->username})"
            : "تم إيقاف حساب ({$user->username})");
    }

    // ==================== تأكيد / رفض رقم الهاتف ====================
    public function confirmUserPhone(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:confirm,reject',
        ]);

        $user = User::findOrFail($id);

        if ($request->action === 'confirm') {
            $user->update(['phone_status' => 'confirmed', 'is_active' => true]);
            NotificationController::sendToUser($user->id, '✅ تم تأكيد رقم هاتفك', 'تم تأكيد رقم هاتفك وتفعيل حسابك بنجاح', 'info');
            $msg = "تم تأكيد رقم الهاتف للمستخدم ({$user->username})";
        } else {
            $user->update(['phone_status' => 'rejected', 'is_active' => false]);
            NotificationController::sendToUser($user->id, '❌ تم رفض رقم الهاتف', 'يرجى مراجعة الإدارة لتحديث رقم هاتفك الصحيح', 'info');
            $msg = "تم رفض رقم الهاتف للمستخدم ({$user->username})";
        }

        AuditService::log('phone_' . $request->action . '_web', $user);

        return back()->with('success', $msg);
    }

    // ==================== إدارة الفروع في الويب ====================
    public function branches()
    {
        $branches = Branch::withCount(['users', 'transfers'])
            ->withSum('transfers', 'amount')
            ->orderBy('created_at', 'desc')
            ->get();

        $allUsers = User::with('branch')->orderBy('username')->get();

        return view('admin.branches', compact('branches', 'allUsers'));
    }

    public function storeBranch(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:100|unique:branches,name',
            'city'         => 'nullable|string|max:100',
            'balance'      => 'nullable|numeric',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);

        $branch = Branch::create([
            'name'         => $request->name,
            'city'         => $request->city,
            'balance'      => $request->balance ?? 0,
            'credit_limit' => $request->credit_limit ?? 0,
            'is_active'    => true,
        ]);

        AuditService::log('branch_created_web', $branch);

        return back()->with('success', "تم إنشاء الفرع ({$branch->name}) بنجاح");
    }

    public function updateBranch(Request $request, $id)
    {
        $branch = Branch::findOrFail($id);
        $request->validate([
            'name'         => 'required|string|max:100|unique:branches,name,' . $id,
            'city'         => 'nullable|string|max:100',
            'balance'      => 'nullable|numeric',
            'credit_limit' => 'nullable|numeric|min:0',
            'is_active'    => 'required|boolean',
        ]);

        $branch->update([
            'name'         => $request->name,
            'city'         => $request->city,
            'balance'      => $request->has('balance') ? $request->balance : $branch->balance,
            'credit_limit' => $request->has('credit_limit') ? $request->credit_limit : $branch->credit_limit,
            'is_active'    => (bool) $request->is_active,
        ]);

        AuditService::log('branch_updated_web', $branch);

        return back()->with('success', "تم تحديث بيانات الفرع ({$branch->name}) بنجاح");
    }

    public function transferUserBranch(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $branch = Branch::findOrFail($id);
        $user = User::findOrFail($request->user_id);
        $oldBranchId = $user->branch_id;

        $user->update(['branch_id' => $branch->id]);

        AuditService::log('user_branch_transferred_web', $user, ['branch_id' => $oldBranchId], ['branch_id' => $branch->id]);

        return back()->with('success', "تم نقل الموظف ({$user->username}) إلى فرع ({$branch->name})");
    }

    // ==================== الموافقات المعلقة ====================
    public function approvals()
    {
        $pendingTransfers = Transfer::with(['user', 'branch'])
            ->where('approval_status', 'pending_approval')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $recentlyReviewed = Transfer::with(['user', 'approver', 'branch'])
            ->whereIn('approval_status', ['approved', 'rejected'])
            ->orderBy('approved_at', 'desc')
            ->take(15)
            ->get();

        return view('admin.approvals', compact('pendingTransfers', 'recentlyReviewed'));
    }

    public function approveTransferWeb(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'nullable|string|max:500',
        ]);

        $transfer = Transfer::findOrFail($id);
        if ($transfer->approval_status !== 'pending_approval') {
            return back()->withErrors(['approval' => 'هذه الحوالة لا تنتظر موافقة حالياً']);
        }

        $adminId = session('admin_user_id');

        if ($request->action === 'approve') {
            $transfer->update([
                'approval_status' => 'approved',
                'approved_by'     => $adminId,
                'approved_at'     => now(),
            ]);
            $msg = "تم اعتماد الحوالة رقم {$transfer->trans_number} بنجاح";
            $notifTitle = '✅ تمت الموافقة على حوالتك';
            $notifBody = "تمت الموافقة على الحوالة رقم {$transfer->trans_number}";
        } else {
            // عند رفض الحوالة المعلقة نلغيها ونعيد الرصيد للمرسل
            $sender = $transfer->user;
            if ($sender && $transfer->status === 'pending') {
                $balanceBefore = (float) $sender->balance;
                $sender->increment('balance', $transfer->amount);
                $balanceAfter = (float) $sender->fresh()->balance;

                BalanceTransaction::create([
                    'user_id'        => $sender->id,
                    'type'           => 'credit',
                    'amount'         => $transfer->amount,
                    'description'    => "استرداد مبلغ الحوالة المرفوضة رقم {$transfer->trans_number}",
                    'trans_number'   => $transfer->trans_number,
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $balanceAfter,
                ]);
            }

            $transfer->update([
                'approval_status' => 'rejected',
                'status'          => 'cancelled',
                'approved_by'     => $adminId,
                'approved_at'     => now(),
                'cancel_reason'   => $request->reason ?: 'تم الرفض من قبل الإدارة',
            ]);
            $msg = "تم رفض الحوالة رقم {$transfer->trans_number} وإرجاع المبلغ لحساب المرسل";
            $notifTitle = '❌ تم رفض حوالتك';
            $notifBody = "تم رفض الحوالة رقم {$transfer->trans_number}. السبب: " . ($request->reason ?: 'غير محدد');
        }

        TransferHistoryLog::create([
            'transfer_id' => $transfer->id,
            'user_id'     => $adminId,
            'action'      => $request->action === 'approve' ? 'approved' : 'rejected',
            'description' => $msg,
        ]);

        if ($transfer->user_id) {
            NotificationController::sendToUser(
                $transfer->user_id,
                $notifTitle,
                $notifBody,
                'approval',
                ['trans_number' => $transfer->trans_number, 'transfer_id' => $transfer->id]
            );
        }

        AuditService::log('transfer_' . $request->action . '_web', $transfer);

        return back()->with('success', $msg);
    }

    // ==================== إدارة الحوالات ====================
    public function transfers(Request $request)
    {
        $search = $request->get('search', '');
        $status = $request->get('status', '');
        $company = $request->get('company', '');

        $transfers = Transfer::with(['user', 'branch', 'approver', 'historyLogs.user'])
            ->when($search, fn($q) => $q->where(function ($sub) use ($search) {
                $sub->where('trans_number', 'like', "%{$search}%")
                    ->orWhere('receiver_name', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%")
                    ->orWhere('created_by', 'like', "%{$search}%");
            }))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($company, fn($q) => $q->where('company_type', $company))
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        $companies = Transfer::select('company_type')->distinct()->pluck('company_type');

        return view('admin.transfers', compact('transfers', 'search', 'status', 'company', 'companies'));
    }

    // ==================== تعديل بيانات الحوالة (اسم المستلم / الملاحظات) ====================
    public function updateTransferReceiver(Request $request, $id)
    {
        $request->validate([
            'receiver_name'  => 'required|string|max:150',
            'destination'    => 'required|string|max:100',
            'internal_notes' => 'nullable|string|max:500',
        ]);

        $transfer = Transfer::findOrFail($id);
        $oldData = $transfer->only(['receiver_name', 'destination', 'internal_notes']);

        $transfer->update([
            'receiver_name'  => $request->receiver_name,
            'destination'    => $request->destination,
            'internal_notes' => $request->internal_notes,
        ]);

        TransferHistoryLog::create([
            'transfer_id' => $transfer->id,
            'user_id'     => session('admin_user_id'),
            'action'      => 'updated_by_admin',
            'description' => 'تعديل بيانات المستلم/الملاحظات من لوحة الإدارة',
            'old_data'    => $oldData,
            'new_data'    => $transfer->only(['receiver_name', 'destination', 'internal_notes']),
        ]);

        AuditService::log('transfer_updated_web', $transfer, $oldData, $transfer->only(['receiver_name', 'destination', 'internal_notes']));

        return back()->with('success', "تم تحديث بيانات الحوالة ({$transfer->trans_number}) بنجاح");
    }

    // ==================== إلغاء حوالة واسترداد المبلغ من الويب ====================
    public function cancelTransferWeb(Request $request, $id)
    {
        $request->validate([
            'cancel_reason' => 'nullable|string|max:500',
        ]);

        $transfer = Transfer::findOrFail($id);

        if ($transfer->status !== 'pending') {
            return back()->withErrors(['transfer' => 'لا يمكن إلغاء هذه الحوالة لأنها مدفوعة أو ملغاة مسبقاً']);
        }

        $sender = $transfer->user;
        if ($sender) {
            $balanceBefore = (float) $sender->balance;
            $sender->increment('balance', $transfer->amount);
            $balanceAfter = (float) $sender->fresh()->balance;

            BalanceTransaction::create([
                'user_id'        => $sender->id,
                'type'           => 'credit',
                'amount'         => $transfer->amount,
                'description'    => "استرداد مبلغ إلغاء الحوالة رقم {$transfer->trans_number} من الإدارة",
                'trans_number'   => $transfer->trans_number,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
            ]);

            NotificationController::sendToUser(
                $sender->id,
                '🔄 تم إلغاء الحوالة واسترداد المبلغ',
                "تم إلغاء الحوالة رقم {$transfer->trans_number} وإعادة مبلغ {$transfer->amount} {$transfer->currency} إلى رصيدك",
                'transfer',
                ['trans_number' => $transfer->trans_number]
            );
        }

        $reason = $request->cancel_reason ?: 'إلغاء إداري مع استرداد الرصيد';
        $transfer->update([
            'status'        => 'cancelled',
            'cancel_reason' => $reason,
        ]);

        TransferHistoryLog::create([
            'transfer_id' => $transfer->id,
            'user_id'     => session('admin_user_id'),
            'action'      => 'cancelled',
            'description' => $reason,
        ]);

        AuditService::log('transfer_cancelled_web', $transfer);

        return back()->with('success', "تم إلغاء الحوالة ({$transfer->trans_number}) واسترداد المبلغ لحساب المرسل");
    }

    // ==================== تصدير الحوالات بصيغة CSV من الويب ====================
    public function exportTransfersCsv(Request $request)
    {
        $search = $request->get('search', '');
        $status = $request->get('status', '');
        $company = $request->get('company', '');

        $transfers = Transfer::with(['user', 'branch'])
            ->when($search, fn($q) => $q->where(function ($sub) use ($search) {
                $sub->where('trans_number', 'like', "%{$search}%")
                    ->orWhere('receiver_name', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%");
            }))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($company, fn($q) => $q->where('company_type', $company))
            ->orderBy('created_at', 'desc')
            ->get();

        // إضافة UTF-8 BOM لدعم اللغة العربية في برنامج Excel
        $csvData = "\xEF\xBB\xBF";
        $csvData .= "رقم الحوالة,المبلغ,العملة,الشركة,المرسل,المستلم,الوجهة,الفرع,الحالة,حالة الاعتماد,التاريخ\n";

        foreach ($transfers as $t) {
            $branchName = $t->branch?->name ?? 'الرئيسي';
            $csvData .= "\"{$t->trans_number}\",\"{$t->amount}\",\"{$t->currency}\",\"{$t->company_type}\",\"{$t->created_by}\",\"{$t->receiver_name}\",\"{$t->destination}\",\"{$branchName}\",\"{$t->status}\",\"{$t->approval_status}\",\"{$t->created_at}\"\n";
        }

        return response($csvData, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="transfers_report_' . now()->format('Y_m_d_His') . '.csv"',
        ]);
    }

    // ==================== سجلات التدقيق ====================
    public function auditLogs(Request $request)
    {
        $logs = AuditLog::with('user:id,username')
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        return view('admin.audit_logs', compact('logs'));
    }

    // ==================== الإعدادات المالية (العمولات وأسعار الصرف وخزائن الفروع) ====================
    public function financialSettings()
    {
        $commissionRules = CommissionRule::orderBy('min_amount')->get();
        $exchangeRates = ExchangeRate::with('updater:id,username')->orderBy('id')->get();
        $branches = Branch::withCount(['users', 'transfers'])->orderBy('name')->get();
        $totalCommissions = (float) Transfer::whereNotIn('status', ['cancelled'])->sum('commission_amount');

        $companies = \App\Models\RemittanceCompany::where('is_active', true)->pluck('name')->toArray();
        if (empty($companies)) {
            $companies = [
                'بنك الكريمي', 'بنك الأمل', 'بنك عدن', 'النجم',
                'دادية', 'قطيبي لحظات', 'يمن اكسبرس', 'الشبكة الموحدة',
            ];
        }

        return view('admin.financial_settings', compact(
            'commissionRules',
            'exchangeRates',
            'branches',
            'totalCommissions',
            'companies'
        ));
    }

    public function storeCommissionRuleWeb(Request $request)
    {
        $request->validate([
            'company_type' => 'nullable|string|max:100',
            'currency'     => 'nullable|string|max:20',
            'min_amount'   => 'required|numeric|min:0',
            'max_amount'   => 'nullable|numeric|gt:min_amount',
            'fee_type'     => 'required|in:fixed,percentage',
            'fee_value'    => 'required|numeric|min:0',
        ]);

        $rule = CommissionRule::create([
            'company_type' => $request->company_type ?: null,
            'currency'     => $request->currency ?: null,
            'min_amount'   => $request->min_amount,
            'max_amount'   => $request->max_amount ?: null,
            'fee_type'     => $request->fee_type,
            'fee_value'    => $request->fee_value,
            'is_active'    => true,
        ]);

        AuditService::log('commission_rule_created_web', $rule);

        return back()->with('success', 'تم إضافة شريحة / قاعدة العمولة بنجاح');
    }

    public function toggleCommissionRuleWeb($id)
    {
        $rule = CommissionRule::findOrFail($id);
        $rule->update(['is_active' => !$rule->is_active]);

        AuditService::log('commission_rule_toggled_web', $rule);

        return back()->with('success', 'تم تحديث حالة قاعدة العمولة');
    }

    public function deleteCommissionRuleWeb($id)
    {
        $rule = CommissionRule::findOrFail($id);
        AuditService::log('commission_rule_deleted_web', $rule);
        $rule->delete();

        return back()->with('success', 'تم حذف قاعدة العمولة بنجاح');
    }

    public function upsertExchangeRateWeb(Request $request)
    {
        $request->validate([
            'currency_code' => 'required|string|max:10',
            'currency_name' => 'required|string|max:50',
            'buy_rate'      => 'required|numeric|min:0.0001',
            'sell_rate'     => 'required|numeric|min:0.0001',
        ]);

        $rate = ExchangeRate::updateOrCreate(
            ['currency_code' => strtoupper(trim($request->currency_code))],
            [
                'currency_name' => $request->currency_name,
                'buy_rate'      => $request->buy_rate,
                'sell_rate'     => $request->sell_rate,
                'updated_by'    => session('admin_user_id'),
                'is_active'     => true,
            ]
        );

        AuditService::log('exchange_rate_updated_web', $rate);

        return back()->with('success', "تم حفظ وتحديث سعر صرف ({$rate->currency_name}) بنجاح");
    }

    public function updateBranchTreasuryWeb(Request $request, $id)
    {
        $request->validate([
            'balance'      => 'required|numeric',
            'credit_limit' => 'required|numeric|min:0',
        ]);

        $branch = Branch::findOrFail($id);
        $old = $branch->only(['balance', 'credit_limit']);

        $branch->update([
            'balance'      => $request->balance,
            'credit_limit' => $request->credit_limit,
        ]);

        AuditService::log('branch_treasury_updated_web', $branch, $old, $branch->only(['balance', 'credit_limit']));

        return back()->with('success', "تم تحديث خزينة وسقف ائتمان فرع ({$branch->name}) بنجاح");
    }

    // ==================== إعدادات النظام والشركات والوجهات الديناميكية (الجزء 3) ====================
    public function systemSettings()
    {
        $settings = [
            'approval_threshold_amount' => (float) \App\Models\SystemSetting::getValue('approval_threshold_amount', 50000),
            'max_login_attempts'        => (int) \App\Models\SystemSetting::getValue('max_login_attempts', 5),
            'lockout_minutes'           => (int) \App\Models\SystemSetting::getValue('lockout_minutes', 15),
            'system_maintenance_mode'   => (string) \App\Models\SystemSetting::getValue('system_maintenance_mode', '0') === '1',
        ];

        $companies = \App\Models\RemittanceCompany::orderBy('id')->get();
        $destinations = \App\Models\Destination::orderBy('id')->get();

        return view('admin.settings', compact('settings', 'companies', 'destinations'));
    }

    public function saveSystemSettingsWeb(Request $request)
    {
        $request->validate([
            'approval_threshold_amount' => 'required|numeric|min:0',
            'max_login_attempts'        => 'required|integer|min:1|max:50',
            'lockout_minutes'           => 'required|integer|min:1|max:1440',
        ]);

        \App\Models\SystemSetting::setValue(
            'approval_threshold_amount',
            $request->approval_threshold_amount,
            'السقف المالي الذي يتطلب موافقة مشرف أو مدير للحوالة (بالريال)'
        );
        \App\Models\SystemSetting::setValue(
            'max_login_attempts',
            $request->max_login_attempts,
            'الحد الأقصى لمحاولات تسجيل الدخول الفاشلة قبل الحظر المؤقت'
        );
        \App\Models\SystemSetting::setValue(
            'lockout_minutes',
            $request->lockout_minutes,
            'مدة حظر الحساب بالدقائق بعد تجاوز محاولات الدخول المسموحة'
        );
        \App\Models\SystemSetting::setValue(
            'system_maintenance_mode',
            $request->has('system_maintenance_mode') ? '1' : '0',
            'وضع الصيانة المؤقت (إيقاف إرسال الحوالات مؤقتاً لجميع المستخدمين)'
        );

        AuditService::log('system_settings_updated_web', null, null, [
            'approval_threshold_amount' => $request->approval_threshold_amount,
            'max_login_attempts'        => $request->max_login_attempts,
            'lockout_minutes'           => $request->lockout_minutes,
            'system_maintenance_mode'   => $request->has('system_maintenance_mode'),
        ]);

        return back()->with('success', 'تم حفظ سياسات وإعدادات النظام بنجاح');
    }

    public function storeCompanyWeb(Request $request)
    {
        $request->validate([
            'name'   => 'required|string|max:100',
            'prefix' => 'required|string|max:10',
        ]);

        $company = \App\Models\RemittanceCompany::create([
            'name'      => trim($request->name),
            'prefix'    => strtoupper(trim($request->prefix)),
            'is_active' => true,
        ]);

        AuditService::log('remittance_company_created_web', $company);

        return back()->with('success', "تم إضافة شركة التحويل ({$company->name} - {$company->prefix}) بنجاح");
    }

    public function toggleCompanyWeb($id)
    {
        $company = \App\Models\RemittanceCompany::findOrFail($id);
        $company->update(['is_active' => !$company->is_active]);

        AuditService::log('remittance_company_toggled_web', $company);

        return back()->with('success', "تم تحديث حالة شركة ({$company->name})");
    }

    public function deleteCompanyWeb($id)
    {
        $company = \App\Models\RemittanceCompany::findOrFail($id);
        $name = $company->name;
        AuditService::log('remittance_company_deleted_web', $company);
        $company->delete();

        return back()->with('success', "تم حذف شركة ({$name}) بنجاح");
    }

    public function storeDestinationWeb(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:120',
            'province' => 'nullable|string|max:80',
        ]);

        $destination = \App\Models\Destination::create([
            'name'      => trim($request->name),
            'province'  => $request->province ? trim($request->province) : null,
            'is_active' => true,
        ]);

        AuditService::log('destination_created_web', $destination);

        return back()->with('success', "تم إضافة وجهة التحويل ({$destination->name}) بنجاح");
    }

    public function toggleDestinationWeb($id)
    {
        $destination = \App\Models\Destination::findOrFail($id);
        $destination->update(['is_active' => !$destination->is_active]);

        AuditService::log('destination_toggled_web', $destination);

        return back()->with('success', "تم تحديث حالة الوجهة ({$destination->name})");
    }

    public function deleteDestinationWeb($id)
    {
        $destination = \App\Models\Destination::findOrFail($id);
        $name = $destination->name;
        AuditService::log('destination_deleted_web', $destination);
        $destination->delete();

        return back()->with('success', "تم حذف الوجهة ({$name}) بنجاح");
    }

    // ==================== الرقابة والأمان والحظر وطلبات التعديل والاسترداد (الجزء 4) ====================
    public function securityCenter()
    {
        $maxAttempts = (int) \App\Models\SystemSetting::getValue('max_login_attempts', 5);
        $lockoutMinutes = (int) \App\Models\SystemSetting::getValue('lockout_minutes', 15);

        $blockedIdentifiers = \App\Models\LoginAttempt::where('success', false)
            ->where('attempted_at', '>=', now()->subMinutes($lockoutMinutes))
            ->selectRaw('identifier, count(*) as failed_count, max(attempted_at) as last_attempt, max(ip_address) as last_ip')
            ->groupBy('identifier')
            ->having('failed_count', '>=', $maxAttempts)
            ->get()
            ->map(function ($row) {
                $user = User::where('user_number', $row->identifier)->first();
                return [
                    'identifier'   => $row->identifier,
                    'failed_count' => (int) $row->failed_count,
                    'last_attempt' => $row->last_attempt,
                    'last_ip'      => $row->last_ip,
                    'user'         => $user,
                ];
            });

        $recentAttempts = \App\Models\LoginAttempt::orderBy('attempted_at', 'desc')
            ->take(40)
            ->get()
            ->map(function ($attempt) {
                $user = User::where('user_number', $attempt->identifier)->first(['id', 'username', 'role']);
                $attempt->user_info = $user;
                return $attempt;
            });

        $activeSessions = User::withCount('tokens')
            ->having('tokens_count', '>', 0)
            ->orderBy('tokens_count', 'desc')
            ->get();

        $blacklist = \App\Models\BlacklistedEntity::with('creator:id,username')
            ->orderBy('created_at', 'desc')
            ->get();

        $transferRequests = \App\Models\TransferRequest::with(['transfer', 'user:id,username,user_number', 'reviewer:id,username'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.security', compact(
            'blockedIdentifiers',
            'recentAttempts',
            'activeSessions',
            'blacklist',
            'transferRequests',
            'maxAttempts',
            'lockoutMinutes'
        ));
    }

    public function unblockIdentifierWeb(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
        ]);

        \App\Models\LoginAttempt::where('identifier', $request->identifier)
            ->where('success', false)
            ->delete();

        AuditService::log('account_unblocked_web', null, null, ['identifier' => $request->identifier]);

        return back()->with('success', "تم فك الحظر الفوري عن المعرف ({$request->identifier}) بنجاح");
    }

    public function revokeUserSessionsWeb($userId)
    {
        $user = User::findOrFail($userId);
        $count = $user->tokens()->count();
        $user->tokens()->delete();

        AuditService::log('user_sessions_revoked_web', $user, ['tokens_count' => $count], ['tokens_count' => 0]);

        return back()->with('success', "تم إنهاء جميع الجلسات النشطة ({$count}) للمستخدم ({$user->username})");
    }

    public function storeBlacklistWeb(Request $request)
    {
        $request->validate([
            'type'   => 'required|in:name,phone,account_number',
            'value'  => 'required|string|max:150',
            'reason' => 'nullable|string|max:255',
        ]);

        $entity = \App\Models\BlacklistedEntity::create([
            'type'      => $request->type,
            'value'     => trim($request->value),
            'reason'    => $request->reason ? trim($request->reason) : 'أسباب أمنية ورقابية',
            'added_by'  => session('admin_user_id'),
            'is_active' => true,
        ]);

        AuditService::log('blacklist_entity_created_web', $entity);

        return back()->with('success', "تم إدراج ({$entity->value}) في القائمة السوداء بنجاح");
    }

    public function toggleBlacklistWeb($id)
    {
        $entity = \App\Models\BlacklistedEntity::findOrFail($id);
        $entity->update(['is_active' => !$entity->is_active]);

        AuditService::log('blacklist_entity_toggled_web', $entity);

        return back()->with('success', "تم تحديث حالة الحظر لـ ({$entity->value})");
    }

    public function deleteBlacklistWeb($id)
    {
        $entity = \App\Models\BlacklistedEntity::findOrFail($id);
        $val = $entity->value;
        AuditService::log('blacklist_entity_deleted_web', $entity);
        $entity->delete();

        return back()->with('success', "تم حذف ({$val}) من القائمة السوداء");
    }

    public function reviewTransferRequestWeb(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
        ]);

        $transferReq = \App\Models\TransferRequest::with(['transfer', 'user'])->findOrFail($id);

        if ($transferReq->status !== 'pending') {
            return back()->withErrors(['request' => 'تمت مراجعة هذا الطلب مسبقاً']);
        }

        $transfer = $transferReq->transfer;
        if (!$transfer || $transfer->status !== 'pending') {
            $transferReq->update([
                'status'      => 'rejected',
                'reviewed_by' => session('admin_user_id'),
                'reviewed_at' => now(),
            ]);
            return back()->withErrors(['request' => 'الحوالة لم تعد معلقة (تم دفعها أو إلغاؤها مسبقاً)']);
        }

        $reviewerId = session('admin_user_id');

        if ($request->action === 'approve') {
            if ($transferReq->request_type === 'edit_receiver') {
                $oldReceiver = $transfer->receiver_name;
                $transfer->update(['receiver_name' => $transferReq->new_receiver_name]);

                TransferHistoryLog::create([
                    'transfer_id' => $transfer->id,
                    'user_id'     => $reviewerId,
                    'action'      => 'receiver_edited_by_request',
                    'description' => "الموافقة على تعديل اسم المستلم من ({$oldReceiver}) إلى ({$transferReq->new_receiver_name})",
                ]);
            } elseif ($transferReq->request_type === 'cancel_refund') {
                $sender = $transfer->user;
                if ($sender) {
                    $balanceBefore = (float) $sender->balance;
                    $sender->increment('balance', $transfer->amount);
                    $balanceAfter = (float) $sender->fresh()->balance;

                    BalanceTransaction::create([
                        'user_id'        => $sender->id,
                        'type'           => 'credit',
                        'amount'         => $transfer->amount,
                        'description'    => "استرداد مبلغ الحوالة رقم {$transfer->trans_number} بناءً على طلب إلغاء معتمد",
                        'trans_number'   => $transfer->trans_number,
                        'balance_before' => $balanceBefore,
                        'balance_after'  => $balanceAfter,
                    ]);
                }

                if ($transfer->branch_id) {
                    $branch = Branch::find($transfer->branch_id);
                    $branch?->increment('balance', $transfer->amount);
                }

                $transfer->update([
                    'status'        => 'cancelled',
                    'cancel_reason' => $transferReq->reason ?: 'إلغاء واسترداد بناءً على طلب الموظف',
                ]);

                TransferHistoryLog::create([
                    'transfer_id' => $transfer->id,
                    'user_id'     => $reviewerId,
                    'action'      => 'cancelled_by_request',
                    'description' => 'اعتماد طلب إلغاء الحوالة واسترداد المبلغ لحساب المرسل',
                ]);
            }

            $transferReq->update([
                'status'      => 'approved',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            NotificationController::sendToUser(
                $transferReq->user_id,
                '✅ تمت الموافقة على طلبك',
                "تم اعتماد طلبك على الحوالة رقم {$transfer->trans_number}",
                'transfer',
                ['trans_number' => $transfer->trans_number]
            );

            AuditService::log('transfer_request_approved_web', $transferReq);

            return back()->with('success', "تم اعتماد الطلب للحوالة ({$transfer->trans_number}) وتنفيذ الأثر المالي بنجاح");
        }

        $transferReq->update([
            'status'      => 'rejected',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        NotificationController::sendToUser(
            $transferReq->user_id,
            '❌ تم رفض طلب التعديل/الإلغاء',
            "تم رفض طلبك على الحوالة رقم {$transfer->trans_number}",
            'transfer',
            ['trans_number' => $transfer->trans_number]
        );

        AuditService::log('transfer_request_rejected_web', $transferReq);

        return back()->with('success', "تم رفض الطلب الخاص بالحوالة ({$transfer->trans_number})");
    }

    // ==================== كشوف الحساب، السندات القابلة للطباعة، والإقفال اليومي (الجزء 5) ====================
    public function statements(Request $request)
    {
        $userId = $request->get('user_id', '');
        $branchId = $request->get('branch_id', '');
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $query = BalanceTransaction::with('user:id,username,user_number,branch_id')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        if ($userId !== '') {
            $query->where('user_id', $userId);
        } elseif ($branchId !== '') {
            $branchUserIds = User::where('branch_id', $branchId)->pluck('id');
            $query->whereIn('user_id', $branchUserIds);
        }

        $transactions = $query->orderBy('created_at', 'asc')->orderBy('id', 'asc')->get();

        $openingBalance = 0.0;
        if ($transactions->isNotEmpty()) {
            $openingBalance = (float) $transactions->first()->balance_before;
        } elseif ($userId !== '') {
            $u = User::find($userId);
            $openingBalance = (float) ($u?->balance ?? 0);
        }

        $totalDebit = (float) $transactions->where('type', 'debit')->sum('amount');
        $totalCredit = (float) $transactions->where('type', 'credit')->sum('amount');
        $closingBalance = $transactions->isNotEmpty()
            ? (float) $transactions->last()->balance_after
            : ($openingBalance + $totalCredit - $totalDebit);

        if ($request->get('export') === 'csv') {
            $csv = "\xEF\xBB\xBF";
            $csv .= "رقم السند,التاريخ,الموظف,رقم المرجع/الحوالة,البيان,مدين (Debit),دائن (Credit),الرصيد التراكمي\n";
            foreach ($transactions as $t) {
                $debit = $t->type === 'debit' ? $t->amount : 0;
                $credit = $t->type === 'credit' ? $t->amount : 0;
                $username = $t->user?->username ?? '-';
                $ref = $t->trans_number ?? "VCH-{$t->id}";
                $csv .= "\"{$t->id}\",\"{$t->created_at}\",\"{$username}\",\"{$ref}\",\"{$t->description}\",\"{$debit}\",\"{$credit}\",\"{$t->balance_after}\"\n";
            }

            return response($csv, 200, [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="account_statement_' . now()->format('Y_m_d_His') . '.csv"',
            ]);
        }

        $users = User::orderBy('username')->get(['id', 'username', 'user_number', 'balance']);
        $branches = Branch::orderBy('name')->get(['id', 'name', 'code']);

        return view('admin.statements', compact(
            'transactions',
            'users',
            'branches',
            'userId',
            'branchId',
            'startDate',
            'endDate',
            'openingBalance',
            'totalDebit',
            'totalCredit',
            'closingBalance'
        ));
    }

    public function printTransferReceipt($id)
    {
        $transfer = Transfer::with(['user', 'branch', 'approver'])->findOrFail($id);
        return view('admin.print_receipt', [
            'mode'     => 'transfer',
            'transfer' => $transfer,
        ]);
    }

    public function printTransactionVoucher($id)
    {
        $transaction = BalanceTransaction::with('user.branch')->findOrFail($id);
        return view('admin.print_receipt', [
            'mode'        => 'voucher',
            'transaction' => $transaction,
        ]);
    }

    public function dailyClosing(Request $request)
    {
        $date = $request->get('date', today()->toDateString());

        $sentTransfers = Transfer::whereDate('created_at', $date)
            ->whereNotIn('status', ['cancelled'])
            ->get();

        $paidRecords = \App\Models\DownloadedTransfer::whereDate('created_at', $date)->get();
        $paidTransNumbers = $paidRecords->pluck('trans_number');
        $paidTransfers = Transfer::whereIn('trans_number', $paidTransNumbers)->get();

        $summary = [
            'sent_count'   => $sentTransfers->count(),
            'sent_amount'  => (float) $sentTransfers->sum('amount'),
            'paid_count'   => $paidTransfers->count(),
            'paid_amount'  => (float) $paidTransfers->sum('amount'),
            'commissions'  => (float) $sentTransfers->sum('commission_amount'),
            'net_movement' => (float) $sentTransfers->sum('amount') - (float) $paidTransfers->sum('amount'),
        ];

        $branchClosing = Branch::orderBy('name')->get()->map(function ($branch) use ($date) {
            $bSent = Transfer::where('branch_id', $branch->id)
                ->whereDate('created_at', $date)
                ->whereNotIn('status', ['cancelled']);

            $branchUserIds = User::where('branch_id', $branch->id)->pluck('id');
            $bPaidNums = \App\Models\DownloadedTransfer::whereIn('user_id', $branchUserIds)
                ->whereDate('created_at', $date)
                ->pluck('trans_number');

            $bPaidAmount = (float) Transfer::whereIn('trans_number', $bPaidNums)->sum('amount');
            $bSentCount = (clone $bSent)->count();
            $bSentAmount = (float) (clone $bSent)->sum('amount');
            $bCommissions = (float) (clone $bSent)->sum('commission_amount');

            return [
                'branch'       => $branch,
                'sent_count'   => $bSentCount,
                'sent_amount'  => $bSentAmount,
                'paid_count'   => $bPaidNums->count(),
                'paid_amount'  => $bPaidAmount,
                'commissions'  => $bCommissions,
                'net_movement' => $bSentAmount - $bPaidAmount,
            ];
        });

        $userClosing = User::with('branch:id,name')->orderBy('username')->get()->map(function ($user) use ($date) {
            $uSent = Transfer::where('user_id', $user->id)
                ->whereDate('created_at', $date)
                ->whereNotIn('status', ['cancelled']);

            $uPaidNums = \App\Models\DownloadedTransfer::where('user_id', $user->id)
                ->whereDate('created_at', $date)
                ->pluck('trans_number');

            $uSentCount = (clone $uSent)->count();
            $uSentAmount = (float) (clone $uSent)->sum('amount');
            $uCommissions = (float) (clone $uSent)->sum('commission_amount');
            $uPaidCount = $uPaidNums->count();
            $uPaidAmount = (float) Transfer::whereIn('trans_number', $uPaidNums)->sum('amount');

            return [
                'user'         => $user,
                'sent_count'   => $uSentCount,
                'sent_amount'  => $uSentAmount,
                'paid_count'   => $uPaidCount,
                'paid_amount'  => $uPaidAmount,
                'commissions'  => $uCommissions,
                'net_movement' => $uSentAmount - $uPaidAmount,
            ];
        });

        return view('admin.daily_closing', compact('date', 'summary', 'branchClosing', 'userClosing'));
    }
}
