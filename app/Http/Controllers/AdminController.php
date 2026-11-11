<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    // ==================== إنشاء مستخدم جديد (من قبل الأدمن) ====================
    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|min:3',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'balance'  => 'nullable|numeric|min:0',
            'role'     => 'required|in:Admin,User',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $user = User::create([
            'username' => $request->username,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'balance'  => $request->balance ?? 0,
            'role'     => $request->role,
        ]);

        \App\Services\AuditService::log('user_created', $user);

        return response()->json([
            'message' => 'تم إضافة المستخدم بنجاح',
            'user'    => $user
        ], 201);
    }

    // ==================== جلب كل المستخدمين ====================
    public function getAllUsers(Request $request)
    {
        $query = User::select('id', 'username', 'email', 'balance', 'role', 'created_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->get();
        return response()->json($users);
    }

    // ==================== تحديث رصيد المستخدم ====================
    public function updateBalance(Request $request, $id)
    {
        $request->validate([
            'balance' => 'required|numeric|min:0',
        ]);

        $user = User::findOrFail($id);
        $oldBalance = $user->balance;
        $user->update(['balance' => $request->balance]);

        \App\Services\AuditService::log('balance_updated', $user, ['balance' => $oldBalance], ['balance' => $request->balance]);

        return response()->json([
            'message' => 'تم تحديث الرصيد بنجاح',
            'user'    => $user
        ]);
    }

    // ==================== حذف مستخدم ====================
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        \App\Services\AuditService::log('user_deleted', $user);
        $user->delete();

        return response()->json(['message' => 'تم حذف المستخدم بنجاح']);
    }

    // ==================== سجل العمليات (Audit Logs) ====================
    public function auditLogs(Request $request)
    {
        $query = \App\Models\AuditLog::with('user:id,username,email')
            ->orderBy('created_at', 'desc');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $logs = $query->paginate(20);
        return response()->json($logs);
    }

    // ==================== لوحة التحكم الإدارية (Dashboard) ====================
    public function dashboard(Request $request)
    {
        $totalUsers = User::count();
        $totalTransfers = \App\Models\Transfer::count();
        $totalSentAmount = \App\Models\Transfer::where('status', 'pending')->sum('amount');
        $totalReceivedAmount = \App\Models\Transfer::where('status', 'paid')->sum('amount');
        $totalCommissions = \App\Models\Transfer::whereNotIn('status', ['cancelled'])->sum('commission_amount');

        $byCompany = \App\Models\Transfer::selectRaw('company_type, count(*) as count, sum(amount) as total, sum(commission_amount) as commissions')
            ->groupBy('company_type')
            ->get();

        $recentTransfers = \App\Models\Transfer::orderBy('created_at', 'desc')->take(10)->get();

        return response()->json([
            'summary' => [
                'total_users'           => $totalUsers,
                'total_transfers'       => $totalTransfers,
                'total_amount_sent'     => (float) $totalSentAmount,
                'total_amount_received' => (float) $totalReceivedAmount,
                'total_commissions'     => (float) $totalCommissions,
            ],
            'by_company'       => $byCompany,
            'recent_transfers' => $recentTransfers,
        ]);
    }

    public function confirmPhone(Request $request, $userId)
    {
        $request->validate([
            'action' => 'required|in:confirm,reject',
        ]);

        $user = User::findOrFail($userId);

        if ($request->action === 'confirm') {
            $user->update([
                'phone_status' => 'confirmed',
                'is_active'    => true,
            ]);
            $message = 'تم تأكيد رقم الجوال وتفعيل الحساب';
        } else {
            $user->update([
                'phone_status' => 'rejected',
                'is_active'    => false,
            ]);
            $message = 'تم رفض رقم الجوال';
        }

        \App\Services\AuditService::log('phone_' . $request->action, $user);

        return response()->json(['message' => $message, 'user' => $user]);
    }

    public function pendingPhoneConfirmations()
    {
        $users = User::where('phone_status', 'pending')
            ->whereNotNull('phone')
            ->select('id', 'username', 'email', 'phone', 'user_number', 'created_at')
            ->get();

        return response()->json($users);
    }

    // ==================== تحديث الحد اليومي للمستخدم ====================
    public function updateDailyLimit(Request $request, $id)
    {
        $request->validate([
            'daily_transfer_limit'       => 'nullable|numeric|min:0',
            'daily_transfer_count_limit' => 'nullable|integer|min:0',
        ]);

        $user = User::findOrFail($id);
        $updates = [];
        if ($request->has('daily_transfer_limit')) {
            $updates['daily_transfer_limit'] = $request->daily_transfer_limit;
        }
        if ($request->has('daily_transfer_count_limit')) {
            $updates['daily_transfer_count_limit'] = $request->daily_transfer_count_limit;
        }
        $user->update($updates);

        \App\Services\AuditService::log('daily_limit_updated', $user, [], $updates);

        return response()->json([
            'message' => 'تم تحديث الحد اليومي بنجاح',
            'user'    => $user,
        ]);
    }

    // ==================== تغيير دور المستخدم ====================
    public function updateRole(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|in:Admin,Supervisor,User',
        ]);

        $user = User::findOrFail($id);
        $oldRole = $user->role;
        $user->update(['role' => $request->role]);

        \App\Services\AuditService::log('role_updated', $user, ['role' => $oldRole], ['role' => $request->role]);

        return response()->json([
            'message' => 'تم تحديث الدور بنجاح',
            'user'    => $user,
        ]);
    }

    // ==================== إحصائيات موسعة للداشبورد ====================
    public function dashboardStats(Request $request)
    {
        // إحصائيات يومية (آخر 7 أيام)
        $dailyStats = \App\Models\Transfer::selectRaw('DATE(created_at) as date, count(*) as count, sum(amount) as total')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // إحصائيات الفروع
        $branchStats = \App\Models\Branch::withCount('users')
            ->withCount(['transfers as transfers_count'])
            ->get();

        // أعلى مستخدمين نشاطاً
        $topUsers = \App\Models\User::withCount('transfers')
            ->orderBy('transfers_count', 'desc')
            ->take(5)
            ->select('id', 'username', 'email', 'role')
            ->get();

        return response()->json([
            'daily_stats'  => $dailyStats,
            'branch_stats' => $branchStats,
            'top_users'    => $topUsers,
        ]);
    }

    // ==================== الإعدادات المالية (العمولات وأسعار الصرف وصناديق الفروع) ====================
    public function financialSettings()
    {
        return response()->json([
            'commission_rules'  => \App\Models\CommissionRule::orderBy('min_amount')->get(),
            'exchange_rates'    => \App\Models\ExchangeRate::orderBy('id')->get(),
            'branches'          => \App\Models\Branch::orderBy('name')->get(),
            'total_commissions' => (float) \App\Models\Transfer::whereNotIn('status', ['cancelled'])->sum('commission_amount'),
        ]);
    }

    public function storeCommissionRule(Request $request)
    {
        $request->validate([
            'company_type' => 'nullable|string|max:100',
            'currency'     => 'nullable|string|max:20',
            'min_amount'   => 'required|numeric|min:0',
            'max_amount'   => 'nullable|numeric|gt:min_amount',
            'fee_type'     => 'required|in:fixed,percentage',
            'fee_value'    => 'required|numeric|min:0',
            'is_active'    => 'nullable|boolean',
        ]);

        $rule = \App\Models\CommissionRule::create([
            'company_type' => $request->company_type ?: null,
            'currency'     => $request->currency ?: null,
            'min_amount'   => $request->min_amount,
            'max_amount'   => $request->max_amount ?: null,
            'fee_type'     => $request->fee_type,
            'fee_value'    => $request->fee_value,
            'is_active'    => $request->boolean('is_active', true),
        ]);

        \App\Services\AuditService::log('commission_rule_created', $rule);

        return response()->json(['message' => 'تم حفظ قاعدة العمولة بنجاح', 'rule' => $rule], 201);
    }

    public function deleteCommissionRule($id)
    {
        $rule = \App\Models\CommissionRule::findOrFail($id);
        \App\Services\AuditService::log('commission_rule_deleted', $rule);
        $rule->delete();

        return response()->json(['message' => 'تم حذف قاعدة العمولة بنجاح']);
    }

    public function upsertExchangeRate(Request $request)
    {
        $request->validate([
            'currency_code' => 'required|string|max:10',
            'currency_name' => 'required|string|max:50',
            'buy_rate'      => 'required|numeric|min:0.0001',
            'sell_rate'     => 'required|numeric|min:0.0001',
        ]);

        $rate = \App\Models\ExchangeRate::updateOrCreate(
            ['currency_code' => strtoupper($request->currency_code)],
            [
                'currency_name' => $request->currency_name,
                'buy_rate'      => $request->buy_rate,
                'sell_rate'     => $request->sell_rate,
                'updated_by'    => $request->user()?->id,
                'is_active'     => true,
            ]
        );

        \App\Services\AuditService::log('exchange_rate_updated', $rate);

        return response()->json(['message' => 'تم تحديث سعر الصرف بنجاح', 'rate' => $rate]);
    }

    public function updateBranchTreasury(Request $request, $id)
    {
        $request->validate([
            'balance'      => 'required|numeric',
            'credit_limit' => 'required|numeric|min:0',
        ]);

        $branch = \App\Models\Branch::findOrFail($id);
        $old = $branch->only(['balance', 'credit_limit']);
        $branch->update([
            'balance'      => $request->balance,
            'credit_limit' => $request->credit_limit,
        ]);

        \App\Services\AuditService::log('branch_treasury_updated', $branch, $old, $branch->only(['balance', 'credit_limit']));

        return response()->json(['message' => 'تم تحديث سيولة وسقف الفرع بنجاح', 'branch' => $branch]);
    }

    // ==================== إعدادات النظام والشركات والوجهات الديناميكية ====================
    public function systemSettings()
    {
        return response()->json([
            'settings' => [
                'approval_threshold_amount' => (float) \App\Models\SystemSetting::getValue('approval_threshold_amount', 50000),
                'max_login_attempts'        => (int) \App\Models\SystemSetting::getValue('max_login_attempts', 5),
                'lockout_minutes'           => (int) \App\Models\SystemSetting::getValue('lockout_minutes', 15),
                'system_maintenance_mode'   => (string) \App\Models\SystemSetting::getValue('system_maintenance_mode', '0') === '1',
            ],
            'companies'    => \App\Models\RemittanceCompany::orderBy('id')->get(),
            'destinations' => \App\Models\Destination::orderBy('id')->get(),
        ]);
    }

    public function saveSystemSettings(Request $request)
    {
        $request->validate([
            'approval_threshold_amount' => 'required|numeric|min:0',
            'max_login_attempts'        => 'required|integer|min:1|max:50',
            'lockout_minutes'           => 'required|integer|min:1|max:1440',
            'system_maintenance_mode'   => 'nullable|boolean',
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
            $request->boolean('system_maintenance_mode') ? '1' : '0',
            'وضع الصيانة المؤقت (إيقاف إرسال الحوالات مؤقتاً لجميع المستخدمين)'
        );

        \App\Services\AuditService::log('system_settings_updated', null, null, $request->only([
            'approval_threshold_amount',
            'max_login_attempts',
            'lockout_minutes',
            'system_maintenance_mode',
        ]));

        return response()->json(['message' => 'تم حفظ إعدادات وسياسات النظام بنجاح']);
    }

    public function storeCompany(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:100',
            'prefix'    => 'required|string|max:10',
            'logo_url'  => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $company = \App\Models\RemittanceCompany::create([
            'name'      => trim($request->name),
            'prefix'    => strtoupper(trim($request->prefix)),
            'logo_url'  => $request->logo_url,
            'is_active' => $request->boolean('is_active', true),
        ]);

        \App\Services\AuditService::log('remittance_company_created', $company);

        return response()->json(['message' => 'تم إضافة شركة التحويل بنجاح', 'company' => $company], 201);
    }

    public function toggleCompany($id)
    {
        $company = \App\Models\RemittanceCompany::findOrFail($id);
        $company->update(['is_active' => !$company->is_active]);

        \App\Services\AuditService::log('remittance_company_toggled', $company);

        return response()->json(['message' => 'تم تحديث حالة الشركة بنجاح', 'company' => $company]);
    }

    public function deleteCompany($id)
    {
        $company = \App\Models\RemittanceCompany::findOrFail($id);
        \App\Services\AuditService::log('remittance_company_deleted', $company);
        $company->delete();

        return response()->json(['message' => 'تم حذف شركة التحويل بنجاح']);
    }

    public function storeDestination(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:120',
            'province'  => 'nullable|string|max:80',
            'is_active' => 'nullable|boolean',
        ]);

        $destination = \App\Models\Destination::create([
            'name'      => trim($request->name),
            'province'  => $request->province ? trim($request->province) : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        \App\Services\AuditService::log('destination_created', $destination);

        return response()->json(['message' => 'تم إضافة الوجهة بنجاح', 'destination' => $destination], 201);
    }

    public function toggleDestination($id)
    {
        $destination = \App\Models\Destination::findOrFail($id);
        $destination->update(['is_active' => !$destination->is_active]);

        \App\Services\AuditService::log('destination_toggled', $destination);

        return response()->json(['message' => 'تم تحديث حالة الوجهة بنجاح', 'destination' => $destination]);
    }

    public function deleteDestination($id)
    {
        $destination = \App\Models\Destination::findOrFail($id);
        \App\Services\AuditService::log('destination_deleted', $destination);
        $destination->delete();

        return response()->json(['message' => 'تم حذف الوجهة بنجاح']);
    }

    // ==================== الرقابة والأمان والحظر وطلبات التعديل والاسترداد (الجزء 4) ====================
    public function securityOverview()
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
                $user = \App\Models\User::where('user_number', $row->identifier)->first(['id', 'username', 'email', 'user_number', 'role']);
                return [
                    'identifier'   => $row->identifier,
                    'failed_count' => (int) $row->failed_count,
                    'last_attempt' => $row->last_attempt,
                    'last_ip'      => $row->last_ip,
                    'user'         => $user,
                ];
            })
            ->values();

        $recentAttempts = \App\Models\LoginAttempt::orderBy('attempted_at', 'desc')
            ->take(50)
            ->get()
            ->map(function ($attempt) {
                $user = \App\Models\User::where('user_number', $attempt->identifier)->first(['id', 'username', 'role']);
                return [
                    'id'           => $attempt->id,
                    'identifier'   => $attempt->identifier,
                    'ip_address'   => $attempt->ip_address,
                    'success'      => (bool) $attempt->success,
                    'attempted_at' => $attempt->attempted_at?->toDateTimeString(),
                    'username'     => $user?->username,
                    'role'         => $user?->role,
                ];
            });

        $activeSessions = \App\Models\User::withCount('tokens')
            ->having('tokens_count', '>', 0)
            ->orderBy('tokens_count', 'desc')
            ->get(['id', 'username', 'email', 'user_number', 'role']);

        $blacklist = \App\Models\BlacklistedEntity::with('creator:id,username')
            ->orderBy('created_at', 'desc')
            ->get();

        $transferRequests = \App\Models\TransferRequest::with(['transfer', 'user:id,username,user_number', 'reviewer:id,username'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'blocked_accounts'  => $blockedIdentifiers,
            'recent_attempts'   => $recentAttempts,
            'active_sessions'   => $activeSessions,
            'blacklist'         => $blacklist,
            'transfer_requests' => $transferRequests,
            'policy'            => [
                'max_login_attempts' => $maxAttempts,
                'lockout_minutes'    => $lockoutMinutes,
            ],
        ]);
    }

    public function unblockIdentifier(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
        ]);

        \App\Models\LoginAttempt::where('identifier', $request->identifier)
            ->where('success', false)
            ->delete();

        \App\Services\AuditService::log('account_unblocked', null, null, ['identifier' => $request->identifier]);

        return response()->json(['message' => "تم فك الحظر الفوري عن الحساب ({$request->identifier}) بنجاح"]);
    }

    public function revokeUserSessions($userId)
    {
        $user = \App\Models\User::findOrFail($userId);
        $count = $user->tokens()->count();
        $user->tokens()->delete();

        \App\Services\AuditService::log('user_sessions_revoked', $user, ['tokens_count' => $count], ['tokens_count' => 0]);

        return response()->json(['message' => "تم إنهاء جميع الجلسات النشطة ({$count}) للمستخدم {$user->username}"]);
    }

    public function storeBlacklistEntity(Request $request)
    {
        $request->validate([
            'type'   => 'required|in:name,phone,account_number',
            'value'  => 'required|string|max:150',
            'reason' => 'nullable|string|max:255',
        ]);

        $entity = \App\Models\BlacklistedEntity::create([
            'type'      => $request->type,
            'value'     => trim($request->value),
            'reason'    => $request->reason ? trim($request->reason) : 'أسباب رقابية وأمنية',
            'added_by'  => $request->user()?->id,
            'is_active' => true,
        ]);

        \App\Services\AuditService::log('blacklist_entity_created', $entity);

        return response()->json(['message' => 'تمت الإضافة إلى القائمة السوداء بنجاح', 'entity' => $entity], 201);
    }

    public function toggleBlacklistEntity($id)
    {
        $entity = \App\Models\BlacklistedEntity::findOrFail($id);
        $entity->update(['is_active' => !$entity->is_active]);

        \App\Services\AuditService::log('blacklist_entity_toggled', $entity);

        return response()->json(['message' => 'تم تحديث حالة عنصر القائمة السوداء', 'entity' => $entity]);
    }

    public function deleteBlacklistEntity($id)
    {
        $entity = \App\Models\BlacklistedEntity::findOrFail($id);
        \App\Services\AuditService::log('blacklist_entity_deleted', $entity);
        $entity->delete();

        return response()->json(['message' => 'تم الحذف من القائمة السوداء بنجاح']);
    }

    public function reviewTransferRequest(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
        ]);

        $transferReq = \App\Models\TransferRequest::with(['transfer', 'user'])->findOrFail($id);

        if ($transferReq->status !== 'pending') {
            return response()->json(['error' => 'تمت مراجعة هذا الطلب مسبقاً'], 422);
        }

        $transfer = $transferReq->transfer;
        if (!$transfer || $transfer->status !== 'pending') {
            $transferReq->update([
                'status'      => 'rejected',
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
            ]);
            return response()->json(['error' => 'الحوالة المرتبطة لم تعد في حالة معلقة (مدفوعة أو ملغاة)'], 422);
        }

        $reviewerId = $request->user()?->id;

        if ($request->action === 'approve') {
            if ($transferReq->request_type === 'edit_receiver') {
                $oldReceiver = $transfer->receiver_name;
                $transfer->update(['receiver_name' => $transferReq->new_receiver_name]);

                \App\Models\TransferHistoryLog::create([
                    'transfer_id' => $transfer->id,
                    'user_id'     => $reviewerId,
                    'action'      => 'receiver_edited_by_request',
                    'description' => "تمت الموافقة على تعديل اسم المستلم من ({$oldReceiver}) إلى ({$transferReq->new_receiver_name})",
                ]);
            } elseif ($transferReq->request_type === 'cancel_refund') {
                $sender = $transfer->user;
                if ($sender) {
                    $balanceBefore = (float) $sender->balance;
                    $sender->increment('balance', $transfer->amount);
                    $balanceAfter = (float) $sender->fresh()->balance;

                    \App\Models\BalanceTransaction::create([
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
                    $branch = \App\Models\Branch::find($transfer->branch_id);
                    $branch?->increment('balance', $transfer->amount);
                }

                $transfer->update([
                    'status'        => 'cancelled',
                    'cancel_reason' => $transferReq->reason ?: 'إلغاء واسترداد بناءً على طلب الموظف',
                ]);

                \App\Models\TransferHistoryLog::create([
                    'transfer_id' => $transfer->id,
                    'user_id'     => $reviewerId,
                    'action'      => 'cancelled_by_request',
                    'description' => 'تم اعتماد طلب إلغاء الحوالة واسترداد المبلغ للمرسل',
                ]);
            }

            $transferReq->update([
                'status'      => 'approved',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            \App\Http\Controllers\NotificationController::sendToUser(
                $transferReq->user_id,
                '✅ تمت الموافقة على طلبك',
                "تم اعتماد طلبك الخاص بالحوالة رقم {$transfer->trans_number}",
                'transfer',
                ['trans_number' => $transfer->trans_number]
            );

            \App\Services\AuditService::log('transfer_request_approved', $transferReq);

            return response()->json(['message' => 'تم اعتماد الطلب وتنفيذ الأثر المالي بنجاح', 'request' => $transferReq]);
        }

        // في حال الرفض
        $transferReq->update([
            'status'      => 'rejected',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        \App\Http\Controllers\NotificationController::sendToUser(
            $transferReq->user_id,
            '❌ تم رفض طلب التعديل/الإلغاء',
            "تم رفض طلبك الخاص بالحوالة رقم {$transfer->trans_number}",
            'transfer',
            ['trans_number' => $transfer->trans_number]
        );

        \App\Services\AuditService::log('transfer_request_rejected', $transferReq);

        return response()->json(['message' => 'تم رفض الطلب', 'request' => $transferReq]);
    }

    // ==================== كشف الحساب التفصيلي والإقفال اليومي (الجزء 5) ====================
    public function accountStatement(Request $request)
    {
        $userId = $request->get('user_id');
        $branchId = $request->get('branch_id');
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $query = \App\Models\BalanceTransaction::with('user:id,username,user_number,branch_id')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        if ($userId) {
            $query->where('user_id', $userId);
        } elseif ($branchId) {
            $branchUserIds = \App\Models\User::where('branch_id', $branchId)->pluck('id');
            $query->whereIn('user_id', $branchUserIds);
        }

        $transactions = $query->orderBy('created_at', 'asc')->orderBy('id', 'asc')->get();

        $openingBalance = 0.0;
        if ($transactions->isNotEmpty()) {
            $openingBalance = (float) $transactions->first()->balance_before;
        } elseif ($userId) {
            $u = \App\Models\User::find($userId);
            $openingBalance = (float) ($u?->balance ?? 0);
        }

        $totalDebit = (float) $transactions->where('type', 'debit')->sum('amount');
        $totalCredit = (float) $transactions->where('type', 'credit')->sum('amount');
        $closingBalance = $transactions->isNotEmpty()
            ? (float) $transactions->last()->balance_after
            : ($openingBalance + $totalCredit - $totalDebit);

        return response()->json([
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'opening_balance' => $openingBalance,
            'total_debit'     => $totalDebit,
            'total_credit'    => $totalCredit,
            'closing_balance' => $closingBalance,
            'transactions'    => $transactions,
            'users'           => \App\Models\User::orderBy('username')->get(['id', 'username', 'user_number', 'balance', 'branch_id']),
            'branches'        => \App\Models\Branch::orderBy('name')->get(['id', 'name', 'code', 'balance']),
        ]);
    }

    public function dailyClosingReport(Request $request)
    {
        $date = $request->get('date', today()->toDateString());

        $sentTransfers = \App\Models\Transfer::whereDate('created_at', $date)
            ->whereNotIn('status', ['cancelled'])
            ->get();

        $paidRecords = \App\Models\DownloadedTransfer::whereDate('created_at', $date)->get();
        $paidTransNumbers = $paidRecords->pluck('trans_number');
        $paidTransfers = \App\Models\Transfer::whereIn('trans_number', $paidTransNumbers)->get();

        $totalSentCount = $sentTransfers->count();
        $totalSentAmount = (float) $sentTransfers->sum('amount');
        $totalCommissions = (float) $sentTransfers->sum('commission_amount');

        $totalPaidCount = $paidTransfers->count();
        $totalPaidAmount = (float) $paidTransfers->sum('amount');

        $netMovement = $totalSentAmount - $totalPaidAmount;

        // تفصيل حسب الفروع
        $branches = \App\Models\Branch::orderBy('name')->get()->map(function ($branch) use ($date) {
            $bSent = \App\Models\Transfer::where('branch_id', $branch->id)
                ->whereDate('created_at', $date)
                ->whereNotIn('status', ['cancelled']);

            $branchUserIds = \App\Models\User::where('branch_id', $branch->id)->pluck('id');
            $bPaidNums = \App\Models\DownloadedTransfer::whereIn('user_id', $branchUserIds)
                ->whereDate('created_at', $date)
                ->pluck('trans_number');

            $bPaidAmount = (float) \App\Models\Transfer::whereIn('trans_number', $bPaidNums)->sum('amount');
            $bSentCount = (clone $bSent)->count();
            $bSentAmount = (float) (clone $bSent)->sum('amount');
            $bCommissions = (float) (clone $bSent)->sum('commission_amount');

            return [
                'id'           => $branch->id,
                'name'         => $branch->name,
                'code'         => $branch->code,
                'balance'      => (float) $branch->balance,
                'sent_count'   => $bSentCount,
                'sent_amount'  => $bSentAmount,
                'paid_count'   => $bPaidNums->count(),
                'paid_amount'  => $bPaidAmount,
                'commissions'  => $bCommissions,
                'net_movement' => $bSentAmount - $bPaidAmount,
            ];
        });

        // تفصيل حسب الموظفين النشطين اليوم
        $users = \App\Models\User::with('branch:id,name')->orderBy('username')->get()->map(function ($user) use ($date) {
            $uSent = \App\Models\Transfer::where('user_id', $user->id)
                ->whereDate('created_at', $date)
                ->whereNotIn('status', ['cancelled']);

            $uPaidNums = \App\Models\DownloadedTransfer::where('user_id', $user->id)
                ->whereDate('created_at', $date)
                ->pluck('trans_number');

            $uSentCount = (clone $uSent)->count();
            $uSentAmount = (float) (clone $uSent)->sum('amount');
            $uCommissions = (float) (clone $uSent)->sum('commission_amount');
            $uPaidCount = $uPaidNums->count();
            $uPaidAmount = (float) \App\Models\Transfer::whereIn('trans_number', $uPaidNums)->sum('amount');

            return [
                'id'           => $user->id,
                'username'     => $user->username,
                'user_number'  => $user->user_number,
                'branch_name'  => $user->branch?->name ?? 'الرئيسي',
                'balance'      => (float) $user->balance,
                'sent_count'   => $uSentCount,
                'sent_amount'  => $uSentAmount,
                'paid_count'   => $uPaidCount,
                'paid_amount'  => $uPaidAmount,
                'commissions'  => $uCommissions,
                'net_movement' => $uSentAmount - $uPaidAmount,
            ];
        })->values();

        return response()->json([
            'date'    => $date,
            'summary' => [
                'sent_count'   => $totalSentCount,
                'sent_amount'  => $totalSentAmount,
                'paid_count'   => $totalPaidCount,
                'paid_amount'  => $totalPaidAmount,
                'commissions'  => $totalCommissions,
                'net_movement' => $netMovement,
            ],
            'branches' => $branches,
            'users'    => $users,
        ]);
    }
}
