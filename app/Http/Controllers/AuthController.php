<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $user = User::create([
            'username'  => $request->username,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'balance'   => 0.00,
            'role'      => 'User',
            'is_active' => false,
            'phone_status' => 'pending',
        ]);

        if (config('app.admin_email') === $request->email && User::where('role','Admin')->count() === 0) {
            $user->update([
                'role'              => 'Admin',
                'phone_status'      => 'confirmed',
                'is_active'         => true,
                'email_verified_at' => now(),
            ]);
        }

        $otp = sprintf("%06d", mt_rand(100000, 999999));
        
        \Illuminate\Support\Facades\DB::table('email_verifications')->insert([
            'email'      => $user->email,
            'otp'        => Hash::make($otp),
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\EmailVerificationMail($otp));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::info("Email OTP for {$user->email}: {$otp}");
        }

        return response()->json([
            'message'    => 'تم إنشاء الحساب. يرجى التحقق من بريدك الإلكتروني',
            'email'      => $user->email,
            'debug_otp'  => config('app.debug') ? $otp : null,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'user_number' => 'required|string|size:6',
            'password'    => 'required|string',
        ]);

        // ==================== تسجيل محاولة الدخول ====================
        $ip = $request->ip();

        $maxAttempts = (int) \App\Models\SystemSetting::getValue('max_login_attempts', 5);
        $lockoutMinutes = (int) \App\Models\SystemSetting::getValue('lockout_minutes', 15);

        // التحقق من عدد المحاولات الفاشلة حسب إعدادات النظام الديناميكية
        if (\App\Models\LoginAttempt::isBlocked($request->user_number, $maxAttempts, $lockoutMinutes)) {
            return response()->json([
                'error' => "تم حظر حسابك مؤقتاً بسبب محاولات دخول متعددة ({$maxAttempts} محاولات). حاول بعد {$lockoutMinutes} دقيقة.",
                'blocked_until' => now()->addMinutes($lockoutMinutes)->toDateTimeString(),
            ], 429);
        }

        $user = User::where('user_number', $request->user_number)->first();

        // تسجيل المحاولة (فاشلة افتراضياً، تُحدَّث إذا نجحت)
        $loginAttempt = \App\Models\LoginAttempt::create([
            'identifier'   => $request->user_number,
            'ip_address'   => $ip,
            'success'      => false,
            'attempted_at' => now(),
        ]);

        $credentialsValid = $user && Hash::check($request->password, $user->password);

        if ($credentialsValid) {
            // تحديث المحاولة الحالية كناجحة
            $loginAttempt->update(['success' => true]);
        }

        if (!$credentialsValid) {
            return response()->json(['error' => 'رقم المستخدم أو كلمة المرور غير صحيحة'], 401);
        }

        if (!$user->email_verified_at) {
            return response()->json([
                'error'  => 'يرجى التحقق من بريدك الإلكتروني أولاً',
                'email'  => $user->email,
                'action' => 'verify_email',
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        \App\Services\AuditService::log('user_login', $user);

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'user'    => $user,
            'token'   => $token
        ]);
    }
    
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    }

    public function updateImage(Request $request)
    {
        $request->validate(['profileImage' => 'required|string']);
        $request->user()->update(['profileImage' => $request->profileImage]);
        return response()->json(['message' => 'تم تحديث الصورة بنجاح']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $otp = sprintf("%06d", mt_rand(100000, 999999));

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token'      => Hash::make($otp),
                'created_at' => now(),
            ]
        );

        try {
            \Illuminate\Support\Facades\Mail::to($request->email)->send(new \App\Mail\OtpMail($otp));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::info("OTP for {$request->email}: {$otp}");
        }

        return response()->json([
            'message' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني',
            'debug_otp' => config('app.debug') ? $otp : null
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|exists:users,email',
            'otp'      => 'required|string|size:6',
            'password' => 'required|string|min:6',
        ]);

        $record = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record || !Hash::check($request->otp, $record->token)) {
            return response()->json(['error' => 'رمز التحقق غير صحيح'], 400);
        }

        $createdAt = \Carbon\Carbon::parse($record->created_at);
        if ($createdAt->addMinutes(15)->isPast()) {
            return response()->json(['error' => 'رمز التحقق منتهي الصلاحية، يرجى طلب رمز جديد'], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->update(['password' => Hash::make($request->password)]);

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'تم إعادة تعيين كلمة المرور بنجاح']);
    }

    public function updateFcmToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);
        $request->user()->update(['fcm_token' => $request->fcm_token]);
        return response()->json(['message' => 'تم تحديث رمز الإشعار']);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'username' => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'username' => $request->username,
            'email'    => $request->email,
        ]);

        return response()->json([
            'message' => 'تم تحديث البيانات الشخصية بنجاح',
            'user'    => $user->fresh(),
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'كلمة المرور الحالية غير صحيحة'], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json(['message' => 'تم تغيير كلمة المرور بنجاح']);
    }

    public function getDestinations()
    {
        try {
            $destinations = \App\Models\Destination::where('is_active', true)->pluck('name')->toArray();
            if (!empty($destinations)) {
                return response()->json($destinations);
            }
        } catch (\Throwable $e) {
            // fallback
        }

        return response()->json([
            'صنعاء',
            'عدن',
            'تعز',
            'المكلا',
            'إب',
            'ذمار',
            'الحديدية',
            'مأرب',
            'سيئون',
            'عتق'
        ]);
    }

    public function getCompanies()
    {
        try {
            $companies = \App\Models\RemittanceCompany::where('is_active', true)->pluck('name')->toArray();
            if (!empty($companies)) {
                return response()->json($companies);
            }
        } catch (\Throwable $e) {
            // fallback
        }

        return response()->json([
            'بنك الكريمي',
            'بنك الأمل',
            'بنك عدن',
            'النجم',
            'دادية',
            'قطيبي لحظات',
            'يمن اكسبرس',
            'الشبكة الموحدة'
        ]);
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string|size:6',
        ]);

        $record = \Illuminate\Support\Facades\DB::table('email_verifications')
            ->where('email', $request->email)
            ->where('used', false)
            ->latest()
            ->first();

        if (!$record) {
            return response()->json(['error' => 'لم يتم العثور على طلب التحقق'], 400);
        }

        if (now()->isAfter($record->expires_at)) {
            return response()->json(['error' => 'رمز التحقق منتهي الصلاحية'], 400);
        }

        if (!Hash::check($request->otp, $record->otp)) {
            return response()->json(['error' => 'رمز التحقق غير صحيح'], 400);
        }

        \Illuminate\Support\Facades\DB::table('email_verifications')->where('id', $record->id)->update(['used' => true]);

        $user = User::where('email', $request->email)->first();
        $user->update(['email_verified_at' => now()]);

        do {
            $userNumber = sprintf("%06d", mt_rand(100000, 999999));
        } while (User::where('user_number', $userNumber)->exists());

        $user->update(['user_number' => $userNumber]);

        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\UserNumberMail($userNumber, $user->username));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::info("User number for {$user->email}: {$userNumber}");
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'     => 'تم التحقق من بريدك الإلكتروني بنجاح. رقمك المميز أُرسل إلى بريدك',
            'user'        => $user,
            'token'       => $token,
            'user_number' => $userNumber,
        ]);
    }

    public function submitPhone(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:7|max:15',
        ]);

        $user = $request->user();

        if ($user->phone_status === 'confirmed') {
            return response()->json(['error' => 'رقم الجوال مؤكد مسبقاً'], 400);
        }

        $user->update([
            'phone'        => $request->phone,
            'phone_status' => 'pending',
        ]);

        return response()->json([
            'message' => 'تم إرسال طلب تأكيد الجوال للإدارة. سيتم مراجعته قريباً',
            'user'    => $user,
        ]);
    }

    public function addCompanionDevice(Request $request)
    {
        $request->validate([
            'companion_email'    => 'required|email|exists:users,email',
            'companion_password' => 'required|string',
        ]);

        $owner = $request->user();

        if ($owner->email === $request->companion_email) {
            return response()->json(['error' => 'لا يمكنك إضافة حسابك الخاص'], 400);
        }

        if ($owner->parent_user_id !== null) {
            return response()->json(['error' => 'الجهاز التابع لا يمكنه إضافة أجهزة مشاركة'], 403);
        }

        $companion = User::where('email', $request->companion_email)->first();

        if (!Hash::check($request->companion_password, $companion->password)) {
            return response()->json(['error' => 'كلمة مرور الحساب المراد إضافته غير صحيحة'], 401);
        }

        if ($companion->parent_user_id !== null) {
            return response()->json(['error' => 'هذا الحساب مرتبط بمستخدم آخر بالفعل'], 400);
        }

        $companion->update(['parent_user_id' => $owner->id]);

        return response()->json([
            'message'   => 'تم ربط الجهاز المشارك بنجاح',
            'companion' => $companion->only(['id', 'username', 'email']),
        ]);
    }

    public function removeCompanionDevice(Request $request, $companionId)
    {
        $owner = $request->user();
        $companion = User::findOrFail($companionId);

        if ($companion->parent_user_id !== $owner->id) {
            return response()->json(['error' => 'غير مصرح لك بحذف هذا الجهاز'], 403);
        }

        $companion->update(['parent_user_id' => null]);

        return response()->json(['message' => 'تم فك ارتباط الجهاز المشارك']);
    }

    public function getCompanionDevices(Request $request)
    {
        $user = $request->user();
        $companions = User::where('parent_user_id', $user->id)
            ->select('id', 'username', 'email', 'phone', 'created_at')
            ->get();

        return response()->json($companions);
    }
}
