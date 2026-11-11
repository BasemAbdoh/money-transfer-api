<?php
namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    // ==================== جلب كل الفروع ====================
    public function index()
    {
        $branches = Branch::withCount('users')->get();
        return response()->json($branches);
    }

    // ==================== إنشاء فرع جديد (مدير فقط) ====================
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:branches,name',
            'city' => 'nullable|string|max:100',
        ]);

        $branch = Branch::create([
            'name'      => $request->name,
            'city'      => $request->city,
            'is_active' => true,
        ]);

        AuditService::log('branch_created', $branch);

        return response()->json([
            'message' => 'تم إنشاء الفرع بنجاح',
            'branch'  => $branch,
        ], 201);
    }

    // ==================== تحديث فرع ====================
    public function update(Request $request, $id)
    {
        $branch = Branch::findOrFail($id);
        $request->validate([
            'name'      => 'sometimes|string|max:100|unique:branches,name,' . $branch->id,
            'city'      => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $branch->update($request->only(['name', 'city', 'is_active']));

        return response()->json([
            'message' => 'تم تحديث الفرع بنجاح',
            'branch'  => $branch,
        ]);
    }

    // ==================== حذف فرع ====================
    public function destroy($id)
    {
        $branch = Branch::findOrFail($id);

        if ($branch->users()->count() > 0) {
            return response()->json([
                'error' => 'لا يمكن حذف الفرع لأنه يحتوي على موظفين'
            ], 422);
        }

        $branch->delete();
        return response()->json(['message' => 'تم حذف الفرع بنجاح']);
    }

    // ==================== إحصائيات الفرع ====================
    public function stats(Request $request, $id)
    {
        $user = $request->user();

        // المشرف يرى إحصائيات فرعه فقط
        if ($user && $user->role === 'Supervisor' && (int) $user->branch_id !== (int) $id) {
            return response()->json(['error' => 'غير مصرح لك بعرض إحصائيات هذا الفرع'], 403);
        }

        $branch = Branch::with('users:id,username,email,role,branch_id')->findOrFail($id);

        $totalTransfers = Transfer::where('branch_id', $id)->count();
        $pendingTransfers = Transfer::where('branch_id', $id)->where('status', 'pending')->count();
        $totalAmount = Transfer::where('branch_id', $id)->sum('amount');

        return response()->json([
            'branch'            => $branch,
            'users_count'       => $branch->users->count(),
            'total_transfers'   => $totalTransfers,
            'pending_transfers' => $pendingTransfers,
            'total_amount'      => (float) $totalAmount,
        ]);
    }

    // ==================== نقل موظف بين فروع ====================
    public function transferUser(Request $request, $branchId)
    {
        Branch::findOrFail($branchId);

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $oldBranchId = $user->branch_id;
        $user->update(['branch_id' => $branchId]);

        AuditService::log('user_branch_transferred', $user, [
            'branch_id' => $oldBranchId
        ], [
            'branch_id' => $branchId
        ]);

        return response()->json([
            'message' => 'تم نقل الموظف للفرع بنجاح',
            'user'    => $user->fresh()->load('branch'),
        ]);
    }
}
