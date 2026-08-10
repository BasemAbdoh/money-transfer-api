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

        $byCompany = \App\Models\Transfer::selectRaw('company_type, count(*) as count, sum(amount) as total')
            ->groupBy('company_type')
            ->get();

        $recentTransfers = \App\Models\Transfer::orderBy('created_at', 'desc')->take(10)->get();

        return response()->json([
            'summary' => [
                'total_users'           => $totalUsers,
                'total_transfers'       => $totalTransfers,
                'total_amount_sent'     => (float) $totalSentAmount,
                'total_amount_received' => (float) $totalReceivedAmount,
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
}
