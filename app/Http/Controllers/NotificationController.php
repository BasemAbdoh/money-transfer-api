<?php
namespace App\Http\Controllers;

use App\Models\InAppNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // ==================== جلب إشعارات المستخدم ====================
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->inAppNotifications()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $unreadCount = $request->user()
            ->inAppNotifications()
            ->where('is_read', false)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    // ==================== وضع علامة مقروء على إشعار ====================
    public function markAsRead(Request $request, $id)
    {
        $notification = InAppNotification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json(['message' => 'تم تحديد الإشعار كمقروء']);
    }

    // ==================== وضع علامة مقروء على كل الإشعارات ====================
    public function markAllAsRead(Request $request)
    {
        $request->user()
            ->inAppNotifications()
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'تم تحديد جميع الإشعارات كمقروءة']);
    }

    // ==================== حذف إشعار ====================
    public function destroy(Request $request, $id)
    {
        InAppNotification::where('user_id', $request->user()->id)
            ->findOrFail($id)
            ->delete();

        return response()->json(['message' => 'تم حذف الإشعار']);
    }

    // ==================== مساعد: إرسال إشعار داخلي لمستخدم ====================
    public static function sendToUser(int $userId, string $title, string $body, string $type = 'info', array $data = []): void
    {
        try {
            InAppNotification::create([
                'user_id' => $userId,
                'title'   => $title,
                'body'    => $body,
                'type'    => $type,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('InAppNotification Error: ' . $e->getMessage());
        }
    }
}
