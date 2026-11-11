<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminWebMiddleware
{
    /**
     * يحمي لوحة التحكم الويب بالجلسة
     */
    public function handle(Request $request, Closure $next)
    {
        if (!session('admin_user_id')) {
            return redirect()->route('admin.login')->with('error', 'يجب تسجيل الدخول أولاً');
        }
        return $next($request);
    }
}
