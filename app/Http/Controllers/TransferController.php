<?php

namespace App\Http\Controllers;

use App\Models\DownloadedTransfer;
use App\Models\Transfer;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    // ==================== إرسال حوالة جديدة ====================
    public function send(Request $request)
    {
        $request->validate([
            'account_number' => 'required|string',
            'receiver_name'  => 'required|string',
            'receiver_email' => 'nullable|email',
            'amount'         => 'required|numeric|min:1',
            'currency'       => 'required|string',
            'company_type'   => 'required|string',
            'destination'    => 'required|string',
            'notes'          => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        // التحقق من وضع الصيانة المؤقت للنظام
        $maintenanceMode = (string) \App\Models\SystemSetting::getValue('system_maintenance_mode', '0');
        if ($maintenanceMode === '1' && $user->role !== 'Admin') {
            return response()->json([
                'error' => 'النظام في وضع الصيانة المؤقت حالياً ولا يمكن إرسال حوالات جديدة. يرجى المحاولة لاحقاً.',
            ], 503);
        }

        // التحقق من القائمة السوداء (Blacklist) قبل إرسال الحوالة
        $blacklisted = \App\Models\BlacklistedEntity::checkTransfer($request->receiver_name, $request->account_number);
        if ($blacklisted) {
            return response()->json([
                'error'  => "⛔ تنبيه أمني: لا يمكن إتمام الحوالة لأن المستلم أو الحساب مدرج في قائمة الحظر والمراقبة ({$blacklisted->value})",
                'reason' => $blacklisted->reason,
            ], 403);
        }

        $companyPrefixes = [
            'بنك الكريمي'    => 'KRM',
            'بنك الأمل'     => 'AML',
            'بنك عدن'       => 'ADN',
            'النجم'         => 'NJM',
            'دادية'         => 'DDA',
            'قطيبي لحظات'   => 'QTB',
            'يمن اكسبرس'    => 'YEX',
            'الشبكة الموحدة' => 'UNN',
        ];

        $dynamicCompany = \App\Models\RemittanceCompany::where('name', $request->company_type)->first();
        if ($dynamicCompany && !$dynamicCompany->is_active) {
            return response()->json([
                'error' => "شركة ({$request->company_type}) موقوفة مؤقتاً من قِبل الإدارة",
            ], 422);
        }

        $prefix = $dynamicCompany?->prefix ?? ($companyPrefixes[$request->company_type] ?? 'TRN');

        do {
            $transNumber = $prefix . '-' . mt_rand(1000000, 9999999);
        } while (Transfer::where('trans_number', $transNumber)->exists());

        // التحقق من الحد اليومي للعدد
        if ($user->hasExceededDailyCountLimit()) {
            return response()->json([
                'error' => 'لقد تجاوزت الحد اليومي لعدد الحوالات',
                'limit' => $user->daily_transfer_count_limit,
                'used'  => $user->todayTransfersCount(),
            ], 422);
        }

        // التحقق من الحد اليومي للمبلغ
        if ($user->hasExceededDailyAmountLimit((float) $request->amount)) {
            return response()->json([
                'error'     => 'المبلغ يتجاوز حدك اليومي المسموح به',
                'limit'     => (float) $user->daily_transfer_limit,
                'used'      => $user->todayTransfersAmount(),
                'remaining' => max(0, (float) $user->daily_transfer_limit - $user->todayTransfersAmount()),
            ], 422);
        }

        // تحديد إذا كانت الحوالة تحتاج موافقة حسب السقف الديناميكي في إعدادات النظام
        $approvalThreshold = (float) \App\Models\SystemSetting::getValue('approval_threshold_amount', 50000);
        $needsApproval = $request->amount > $approvalThreshold && $user->role === 'User';

        if ($user->balance < $request->amount) {
            return response()->json([
                'error' => "رصيدك غير كافٍ لإكمال العملية (الرصيد الحالي: {$user->balance})",
                'balance' => (float)$user->balance
            ], 422);
        }

        // حساب العمولة تلقائياً حسب قواعد العمولات النشطة
        $commissionAmount = \App\Models\CommissionRule::calculateFee(
            $request->company_type,
            $request->currency,
            (float) $request->amount
        );
        $netAmount = max(0, (float) $request->amount - $commissionAmount);

        $balanceBefore = $user->balance;
        $user->decrement('balance', $request->amount);
        $balanceAfter = $user->fresh()->balance;

        $transfer = Transfer::create([
            'trans_number'      => $transNumber,
            'account_number'    => $request->account_number,
            'receiver_name'     => $request->receiver_name,
            'receiver_email'    => $request->receiver_email,
            'amount'            => $request->amount,
            'commission_amount' => $commissionAmount,
            'net_amount'        => $netAmount,
            'currency'          => $request->currency,
            'company_type'      => $request->company_type,
            'destination'       => $request->destination,
            'created_by'        => $user->username,
            'user_id'           => $user->id,
            'status'            => 'pending',
            'branch_id'         => $user->branch_id,
            'notes'             => $request->input('notes'),
        ]);

        // خصم من صندوق سيولة الفرع إن وُجد وتنبيه المدير عند انخفاض السيولة عن الحد الأدنى
        if ($user->branch_id) {
            $branch = \App\Models\Branch::find($user->branch_id);
            if ($branch) {
                $branch->decrement('balance', $request->amount);
                $branch->refresh();
                if ($branch->credit_limit > 0 && $branch->balance < $branch->credit_limit) {
                    $admins = \App\Models\User::where('role', 'Admin')->pluck('id');
                    foreach ($admins as $adminId) {
                        \App\Http\Controllers\NotificationController::sendToUser(
                            $adminId,
                            '⚠️ تنبيه سيولة فرع منخفضة',
                            "انخفض رصيد سيولة فرع ({$branch->name}) إلى {$branch->balance} وهو أقل من الحد الأدنى ({$branch->credit_limit})",
                            'balance'
                        );
                    }
                }
            }
        }

        // إذا احتاجت موافقة، حدّث حالة الموافقة
        if ($needsApproval) {
            $transfer->update(['approval_status' => 'pending_approval']);
        }

        // تسجيل في تاريخ الحوالة
        \App\Models\TransferHistoryLog::create([
            'transfer_id' => $transfer->id,
            'user_id'     => $user->id,
            'action'      => 'created',
            'description' => 'تم إنشاء الحوالة',
        ]);

        // إشعار داخلي
        \App\Http\Controllers\NotificationController::sendToUser(
            $user->id,
            '💸 تم إرسال الحوالة',
            "تم إرسال حوالة بمبلغ {$request->amount} {$request->currency} برقم {$transNumber}",
            'transfer',
            ['trans_number' => $transNumber]
        );

        // تحذير إذا اقترب من الحد اليومي (80%)
        if ($user->daily_transfer_limit > 0) {
            $usedPercent = ($user->todayTransfersAmount() / $user->daily_transfer_limit) * 100;
            if ($usedPercent >= 80) {
                \App\Http\Controllers\NotificationController::sendToUser(
                    $user->id,
                    '⚠️ تنبيه: اقتربت من حدك اليومي',
                    "لقد استخدمت " . round($usedPercent) . "% من حدك اليومي البالغ {$user->daily_transfer_limit}",
                    'info'
                );
            }
        }

        \App\Models\BalanceTransaction::create([
            'user_id'        => $user->id,
            'type'           => 'debit',
            'amount'         => $request->amount,
            'description'    => "إرسال حوالة رقم {$transNumber}" . ($commissionAmount > 0 ? " (العمولة: {$commissionAmount})" : ""),
            'trans_number'   => $transNumber,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
        ]);

        \App\Services\AuditService::log('transfer_sent', $transfer);

        $this->sendPushNotification($transNumber, $request->amount, $request->currency);

        return response()->json([
            'message'  => 'تم إرسال الحوالة بنجاح',
            'transfer' => $transfer,
            'new_balance' => $balanceAfter
        ], 201);
    }

    private function sendPushNotification($transNumber, $amount, $currency)
    {
        try {
            $serverKey = env('FCM_SERVER_KEY');
            if ($serverKey) {
                \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'key=' . $serverKey,
                    'Content-Type'  => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => '/topics/new-transfer',
                    'notification' => [
                        'title' => 'حوالة جديدة 💸',
                        'body'  => "تم إرسال حوالة جديدة برقم: {$transNumber} بمبلغ {$amount} {$currency}",
                    ],
                    'data' => [
                        'trans_number' => $transNumber,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ]
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('FCM Error: ' . $e->getMessage());
        }
    }

    // ==================== البحث عن حوالة برقمها ====================
    public function search(Request $request)
    {
        $request->validate([
            'trans_number' => 'required|string',
        ]);

        // فحص إذا كانت الحوالة مدفوعة أصلاً
        $paid = DownloadedTransfer::where('trans_number', $request->trans_number)->first();
        if ($paid) {
            return response()->json(
                ['error' => 'هذه الحوالة تم دفعها مسبقاً'],
                409
            );
        }

        $transfer = Transfer::where('trans_number', $request->trans_number)
                            ->where('status', 'pending')
                            ->first();

        if (!$transfer) {
            return response()->json(['error' => 'لم يتم العثور على الحوالة'], 404);
        }

        // الحوالات التي تنتظر الموافقة أو المرفوضة لا يمكن صرفها
        if (in_array($transfer->approval_status, ['pending_approval', 'rejected'])) {
            return response()->json(['error' => 'هذه الحوالة بانتظار الموافقة أو مرفوضة'], 403);
        }

        // التحقق من القائمة السوداء (Blacklist) قبل صرف الحوالة
        $blacklisted = \App\Models\BlacklistedEntity::checkTransfer($transfer->receiver_name, $transfer->account_number);
        if ($blacklisted) {
            return response()->json([
                'error'  => "⛔ تنبيه أمني: لا يمكن صرف هذه الحوالة لوجود تطابق مع قائمة الحظر والمراقبة ({$blacklisted->value})",
                'reason' => $blacklisted->reason,
            ], 403);
        }

        return response()->json([
            'message'  => 'تم العثور على الحوالة',
            'transfer' => $transfer
        ]);
    }

    // ==================== دفع / استلام حوالة ====================
    public function pay(Request $request)
    {
        $request->validate([
            'trans_number' => 'required|string',
        ]);

        $transfer = Transfer::where('trans_number', $request->trans_number)
                            ->where('status', 'pending')
                            ->first();

        if (!$transfer) {
            return response()->json(['error' => 'الحوالة غير موجودة أو مدفوعة مسبقاً'], 404);
        }

        // الحوالات التي تنتظر الموافقة أو المرفوضة لا يمكن صرفها
        if (in_array($transfer->approval_status, ['pending_approval', 'rejected'])) {
            return response()->json(['error' => 'هذه الحوالة بانتظار الموافقة أو مرفوضة'], 403);
        }

        // التحقق من القائمة السوداء (Blacklist) قبل صرف الحوالة
        $blacklisted = \App\Models\BlacklistedEntity::checkTransfer($transfer->receiver_name, $transfer->account_number);
        if ($blacklisted) {
            return response()->json([
                'error'  => "⛔ تنبيه أمني: لا يمكن صرف هذه الحوالة لوجود تطابق مع قائمة الحظر والمراقبة ({$blacklisted->value})",
                'reason' => $blacklisted->reason,
            ], 403);
        }

        $user = $request->user();

        $balanceBefore = $user->balance;
        $user->increment('balance', $transfer->amount);
        $balanceAfter = $user->fresh()->balance;

        // تسجيل عملية الدفع
        DownloadedTransfer::create([
            'trans_number'   => $request->trans_number,
            'downloaded_by'  => $user->username,
            'user_id'        => $user->id,
        ]);

        // سجل حركة الرصيد
        \App\Models\BalanceTransaction::create([
            'user_id'        => $user->id,
            'type'           => 'credit',
            'amount'         => $transfer->amount,
            'description'    => "استلام حوالة رقم {$request->trans_number}",
            'trans_number'   => $request->trans_number,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
        ]);

        // تحديث حالة الحوالة
        $transfer->update(['status' => 'paid']);

        \App\Services\AuditService::log('transfer_paid', $transfer);

        return response()->json([
            'message' => 'تم دفع الحوالة بنجاح',
            'new_balance' => $balanceAfter
        ]);
    }

    // ==================== سجل حركات الرصيد ====================
    public function balanceHistory(Request $request)
    {
        $transactions = \App\Models\BalanceTransaction::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        return response()->json([
            'balance'      => (float)$request->user()->balance,
            'transactions' => $transactions
        ]);
    }

    // ==================== تقرير الحوالات المُرسَلة ====================
    public function sentReport(Request $request)
    {
        $query = $this->scopeTransfersForUser(Transfer::query(), $request->user())
            ->where('status', 'pending');

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('trans_number', 'like', "%{$search}%")
                  ->orWhere('receiver_name', 'like', "%{$search}%");
            });
        }

        $transfers = $query->orderBy('created_at', 'desc')->paginate(20);
        return response()->json($transfers);
    }

    // ==================== تقرير الحوالات المستلمة (المدفوعة) ====================
    public function receivedReport(Request $request)
    {
        $query = $this->scopeTransfersForUser(Transfer::query(), $request->user())
            ->where('status', 'paid');

        if ($request->filled('start_date')) {
            $query->whereDate('updated_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('updated_at', '<=', $request->end_date);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('trans_number', 'like', "%{$search}%")
                  ->orWhere('receiver_name', 'like', "%{$search}%");
            });
        }

        $transfers = $query->orderBy('updated_at', 'desc')->paginate(20);
        return response()->json($transfers);
    }

    // ==================== إحصائيات الحوالات ====================
    public function stats(Request $request)
    {
        $baseQuery = $this->scopeTransfersForUser(Transfer::query(), $request->user());
        $sentCount     = (clone $baseQuery)->where('status', 'pending')->count();
        $sentTotal     = (clone $baseQuery)->where('status', 'pending')->sum('amount');
        $receivedCount = (clone $baseQuery)->where('status', 'paid')->count();
        $receivedTotal = (clone $baseQuery)->where('status', 'paid')->sum('amount');

        return response()->json([
            'sent_count'     => $sentCount,
            'sent_total'     => (float) $sentTotal,
            'received_count' => $receivedCount,
            'received_total' => (float) $receivedTotal,
        ]);
    }

    // ==================== تفاصيل حوالة واحدة ====================
    public function show(Request $request, $id)
    {
        $transfer = $this->scopeTransfersForUser(Transfer::query(), $request->user())
            ->findOrFail($id);

        return response()->json($transfer);
    }

    // ==================== إلغاء حوالة ====================
    public function cancel(Request $request, $id)
    {
        $transfer = Transfer::findOrFail($id);
        $user = $request->user();

        if ($transfer->status !== 'pending') {
            return response()->json(['error' => 'يمكن إلغاء الحوالات المعلقة فقط'], 400);
        }

        if ($transfer->user_id !== $user->id && $user->role !== 'Admin') {
            return response()->json(['error' => 'غير مصرح لك بإلغاء هذه الحوالة'], 403);
        }

        $balanceBefore = $user->balance;
        $user->increment('balance', $transfer->amount);
        $balanceAfter = $user->fresh()->balance;

        $transfer->update(['status' => 'cancelled']);

        \App\Models\BalanceTransaction::create([
            'user_id'        => $user->id,
            'type'           => 'credit',
            'amount'         => $transfer->amount,
            'description'    => "استرداد مبلغ إلغاء الحوالة رقم {$transfer->trans_number}",
            'trans_number'   => $transfer->trans_number,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
        ]);

        \App\Services\AuditService::log('transfer_cancelled', $transfer);

        return response()->json([
            'message'     => 'تم إلغاء الحوالة واسترداد المبلغ بنجاح',
            'new_balance' => $balanceAfter,
        ]);
    }

    // ==================== إضافة ملاحظة على الحوالة ====================
    public function addNote(Request $request, $id)
    {
        $request->validate([
            'notes' => 'required|string|max:500',
        ]);

        $user = $request->user();
        $transfer = \App\Models\Transfer::findOrFail($id);

        // التحقق من الصلاحية: صاحب الحوالة أو مدير/مشرف
        if ($transfer->user_id !== $user->id && !in_array($user->role, ['Admin', 'Supervisor'])) {
            return response()->json(['error' => 'غير مصرح لك بتعديل هذه الحوالة'], 403);
        }

        $oldNotes = $transfer->notes;

        // المدير والمشرف يضيفون ملاحظات داخلية، الموظف يضيف ملاحظاته الخاصة
        if (in_array($user->role, ['Admin', 'Supervisor'])) {
            $transfer->update(['internal_notes' => $request->notes]);
        } else {
            $transfer->update(['notes' => $request->notes]);
        }

        // تسجيل في تاريخ الحوالة
        \App\Models\TransferHistoryLog::create([
            'transfer_id' => $transfer->id,
            'user_id'     => $user->id,
            'action'      => 'notes_updated',
            'description' => 'تم تحديث الملاحظات',
            'old_data'    => ['notes' => $oldNotes],
            'new_data'    => ['notes' => $request->notes],
        ]);

        return response()->json(['message' => 'تم إضافة الملاحظة بنجاح']);
    }

    // ==================== جلب تاريخ الحوالة ====================
    public function transferHistory(Request $request, $id)
    {
        $transfer = $this->scopeTransfersForUser(Transfer::query(), $request->user())
            ->with(['historyLogs.user:id,username'])
            ->findOrFail($id);

        return response()->json([
            'transfer' => $transfer,
            'history'  => $transfer->historyLogs,
        ]);
    }

    // ==================== الموافقة على حوالة (مشرف/مدير) ====================
    public function approveTransfer(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        if (!in_array($user->role, ['Admin', 'Supervisor'])) {
            return response()->json(['error' => 'غير مصرح لك باعتماد الحوالات'], 403);
        }

        $transfer = \App\Models\Transfer::findOrFail($id);

        if ($transfer->approval_status !== 'pending_approval') {
            return response()->json(['error' => 'هذه الحوالة لا تنتظر موافقة'], 422);
        }

        if ($request->action === 'approve') {
            $transfer->update([
                'approval_status' => 'approved',
                'approved_by'     => $user->id,
                'approved_at'     => now(),
            ]);
            $message = 'تم اعتماد الحوالة بنجاح';
            $notifTitle = '✅ تمت الموافقة على حوالتك';
            $notifBody = "تمت الموافقة على الحوالة رقم {$transfer->trans_number}";
        } else {
            $transfer->update([
                'approval_status' => 'rejected',
                'approved_by'     => $user->id,
                'approved_at'     => now(),
                'cancel_reason'   => $request->reason ?? 'تم الرفض من قبل المشرف',
            ]);
            $message = 'تم رفض الحوالة';
            $notifTitle = '❌ تم رفض حوالتك';
            $notifBody = "تم رفض الحوالة رقم {$transfer->trans_number}. السبب: " . ($request->reason ?? 'غير محدد');
        }

        // تسجيل في التاريخ
        \App\Models\TransferHistoryLog::create([
            'transfer_id' => $transfer->id,
            'user_id'     => $user->id,
            'action'      => $request->action === 'approve' ? 'approved' : 'rejected',
            'description' => $message,
        ]);

        // إشعار داخلي لصاحب الحوالة
        \App\Http\Controllers\NotificationController::sendToUser(
            $transfer->user_id, $notifTitle, $notifBody, 'approval',
            ['trans_number' => $transfer->trans_number, 'transfer_id' => $transfer->id]
        );

        \App\Services\AuditService::log('transfer_' . $request->action, $transfer);

        return response()->json(['message' => $message, 'transfer' => $transfer->fresh()]);
    }

    // ==================== قائمة الحوالات المنتظرة موافقة ====================
    public function pendingApprovals(Request $request)
    {
        $user = $request->user();
        $query = \App\Models\Transfer::with('user:id,username,branch_id')
            ->where('approval_status', 'pending_approval')
            ->orderBy('created_at', 'desc');

        // المشرف يرى فقط حوالات فرعه
        if ($user->role === 'Supervisor') {
            $user->branch_id
                ? $query->where('branch_id', $user->branch_id)
                : $query->whereRaw('1 = 0');
        }

        $transfers = $query->paginate(20);
        return response()->json($transfers);
    }

    // ==================== إحصائيات الحد اليومي ====================
    public function dailyLimitStatus(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'daily_amount_limit'       => (float) $user->daily_transfer_limit,
            'daily_count_limit'        => $user->daily_transfer_count_limit,
            'today_amount_used'        => $user->todayTransfersAmount(),
            'today_count_used'         => $user->todayTransfersCount(),
            'amount_remaining'         => max(0, (float) $user->daily_transfer_limit - $user->todayTransfersAmount()),
            'count_remaining'          => max(0, $user->daily_transfer_count_limit - $user->todayTransfersCount()),
            'has_amount_limit'         => $user->daily_transfer_limit > 0,
            'has_count_limit'          => $user->daily_transfer_count_limit > 0,
        ]);
    }

    // ==================== تصفية متقدمة للتقارير ====================
    public function advancedReport(Request $request)
    {
        $request->validate([
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date',
            'company'     => 'nullable|string',
            'currency'    => 'nullable|string',
            'status'      => 'nullable|in:pending,paid',
            'min_amount'  => 'nullable|numeric|min:0',
            'max_amount'  => 'nullable|numeric',
            'user_id'     => 'nullable|integer',
            'branch_id'   => 'nullable|integer',
            'type'        => 'nullable|in:sent,received,all',
        ]);

        $user = $request->user();
        $query = $this->scopeTransfersForUser(\App\Models\Transfer::query(), $user)
            ->with('user:id,username');

        // فلترة حسب نوع التقرير
        $type = $request->type ?? 'sent';
        if ($type === 'sent') {
            $query->where('user_id', $user->id);
        } elseif ($type === 'received') {
            // الحوالات المستلمة (الحوالات المدفوعة بواسطة هذا المستخدم في downloaded_transfers)
            $downloadedNums = \App\Models\DownloadedTransfer::where('downloaded_by', $user->username)
                ->pluck('trans_number');
            $query->whereIn('trans_number', $downloadedNums);
        } elseif (in_array($user->role, ['Admin', 'Supervisor'])) {
            // المدير والمشرف يرون الكل
            if ($user->role === 'Supervisor' && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            }
        } else {
            $query->where('user_id', $user->id);
        }

        // تطبيق الفلاتر
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        if ($request->filled('company')) {
            $query->where('company_type', $request->company);
        }
        if ($request->filled('currency')) {
            $query->where('currency', $request->currency);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', $request->min_amount);
        }
        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', $request->max_amount);
        }
        if ($request->filled('user_id') && in_array($user->role, ['Admin', 'Supervisor'])) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('branch_id') && $user->role === 'Admin') {
            $query->where('branch_id', $request->branch_id);
        }

        $transfers = $query->orderBy('created_at', 'desc')->paginate(25);

        // إحصائيات مجمعة
        $statsQuery = clone $query;
        $stats = [
            'total_count'  => $statsQuery->count(),
            'total_amount' => (float) $statsQuery->sum('amount'),
            'by_company'   => (clone $query)->selectRaw('company_type, count(*) as count, sum(amount) as total')
                ->groupBy('company_type')->get(),
            'by_currency'  => (clone $query)->selectRaw('currency, count(*) as count, sum(amount) as total')
                ->groupBy('currency')->get(),
        ];

        return response()->json([
            'transfers' => $transfers,
            'stats'     => $stats,
        ]);
    }

    // ==================== تصدير التقرير بصيغة CSV ====================
    public function exportCsv(Request $request)
    {
        $user = $request->user();

        $query = $this->scopeTransfersForUser(\App\Models\Transfer::query(), $user);

        // فلاتر التقرير
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('company')) {
            $query->where('company_type', $request->company);
        }

        $transfers = $query->orderBy('created_at', 'desc')->get();

        // بناء محتوى CSV
        $csvData = "رقم الحوالة,المبلغ,العملة,الشركة,المستلم,الوجهة,الحالة,التاريخ\n";
        foreach ($transfers as $t) {
            $csvData .= "\"{$t->trans_number}\",\"{$t->amount}\",\"{$t->currency}\",\"{$t->company_type}\",\"{$t->receiver_name}\",\"{$t->destination}\",\"{$t->status}\",\"{$t->created_at}\"\n";
        }

        return response($csvData, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="transfers_report_' . now()->format('Y_m_d') . '.csv"',
        ]);
    }

    // ==================== طلبات تعديل المستلم أو إلغاء واسترداد الحوالة (الجزء 4) ====================
    public function storeModificationRequest(Request $request, $id)
    {
        $request->validate([
            'request_type'      => 'required|in:edit_receiver,cancel_refund',
            'new_receiver_name' => 'required_if:request_type,edit_receiver|nullable|string|max:150',
            'reason'            => 'required|string|max:500',
        ]);

        $user = $request->user();
        $transfer = Transfer::findOrFail($id);

        if ($transfer->status !== 'pending') {
            return response()->json(['error' => 'لا يمكن تقديم طلب تعديل أو إلغاء لحوالة مدفوعة أو ملغاة'], 422);
        }

        if ($transfer->user_id !== $user->id && !in_array($user->role, ['Admin', 'Supervisor'])) {
            return response()->json(['error' => 'غير مصرح لك بتقديم طلب لهذه الحوالة'], 403);
        }

        $existingPending = \App\Models\TransferRequest::where('transfer_id', $transfer->id)
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            return response()->json(['error' => 'يوجد طلب قيد المراجعة بالفعل لهذه الحوالة'], 422);
        }

        $transferReq = \App\Models\TransferRequest::create([
            'transfer_id'       => $transfer->id,
            'user_id'           => $user->id,
            'request_type'      => $request->request_type,
            'new_receiver_name' => $request->request_type === 'edit_receiver' ? trim($request->new_receiver_name) : null,
            'reason'            => trim($request->reason),
            'status'            => 'pending',
        ]);

        \App\Models\TransferHistoryLog::create([
            'transfer_id' => $transfer->id,
            'user_id'     => $user->id,
            'action'      => 'modification_requested',
            'description' => $request->request_type === 'edit_receiver'
                ? "طلب تعديل اسم المستلم إلى ({$request->new_receiver_name})"
                : "طلب إلغاء واسترداد الحوالة: {$request->reason}",
        ]);

        // إشعار للمدراء والمشرفين
        $managers = \App\Models\User::whereIn('role', ['Admin', 'Supervisor'])->pluck('id');
        foreach ($managers as $managerId) {
            \App\Http\Controllers\NotificationController::sendToUser(
                $managerId,
                '📝 طلب تعديل/إلغاء حوالة جديد',
                "قدّم الموظف ({$user->username}) طلباً على الحوالة رقم {$transfer->trans_number}",
                'transfer',
                ['trans_number' => $transfer->trans_number]
            );
        }

        return response()->json([
            'message' => 'تم رفع الطلب للإدارة بنجاح وسيتم مراجعته قريباً',
            'request' => $transferReq,
        ], 201);
    }

    public function myModificationRequests(Request $request)
    {
        $user = $request->user();
        $query = \App\Models\TransferRequest::with(['transfer', 'user:id,username', 'reviewer:id,username'])
            ->orderBy('created_at', 'desc');

        if (!in_array($user->role, ['Admin', 'Supervisor'])) {
            $query->where('user_id', $user->id);
        }

        return response()->json($query->get());
    }

    private function scopeTransfersForUser($query, $user)
    {
        if ($user->role === 'Admin') {
            return $query;
        }

        if ($user->role === 'Supervisor') {
            return $user->branch_id
                ? $query->where('branch_id', $user->branch_id)
                : $query->whereRaw('1 = 0');
        }

        return $query->where('user_id', $user->id);
    }
}
