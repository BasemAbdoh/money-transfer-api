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
        ]);

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
        $prefix = $companyPrefixes[$request->company_type] ?? 'TRN';

        do {
            $transNumber = $prefix . '-' . mt_rand(1000000, 9999999);
        } while (Transfer::where('trans_number', $transNumber)->exists());

        $user = $request->user();

        if ($user->balance < $request->amount) {
            return response()->json([
                'error' => "رصيدك غير كافٍ لإكمال العملية (الرصيد الحالي: {$user->balance})",
                'balance' => (float)$user->balance
            ], 422);
        }

        $balanceBefore = $user->balance;
        $user->decrement('balance', $request->amount);
        $balanceAfter = $user->fresh()->balance;

        $transfer = Transfer::create([
            'trans_number'   => $transNumber,
            'account_number' => $request->account_number,
            'receiver_name'  => $request->receiver_name,
            'receiver_email' => $request->receiver_email,
            'amount'         => $request->amount,
            'currency'       => $request->currency,
            'company_type'   => $request->company_type,
            'destination'    => $request->destination,
            'created_by'     => $user->username,
            'user_id'        => $user->id,
            'status'         => 'pending',
        ]);

        \App\Models\BalanceTransaction::create([
            'user_id'        => $user->id,
            'type'           => 'debit',
            'amount'         => $request->amount,
            'description'    => "إرسال حوالة رقم {$transNumber}",
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
        $query = Transfer::where('status', 'pending');

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
        $query = Transfer::where('status', 'paid');

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
        $sentCount     = Transfer::where('status', 'pending')->count();
        $sentTotal     = Transfer::where('status', 'pending')->sum('amount');
        $receivedCount = Transfer::where('status', 'paid')->count();
        $receivedTotal = Transfer::where('status', 'paid')->sum('amount');

        return response()->json([
            'sent_count'     => $sentCount,
            'sent_total'     => (float) $sentTotal,
            'received_count' => $receivedCount,
            'received_total' => (float) $receivedTotal,
        ]);
    }

    // ==================== تفاصيل حوالة واحدة ====================
    public function show($id)
    {
        $transfer = Transfer::findOrFail($id);
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
}
