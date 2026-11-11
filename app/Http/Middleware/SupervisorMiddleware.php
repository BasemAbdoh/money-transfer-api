<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupervisorMiddleware
{
    /**
     * يسمح فقط للمشرفين والمديرين بالمرور
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, ['Admin', 'Supervisor'])) {
            return response()->json([
                'error' => 'هذه العملية تتطلب صلاحية مشرف أو مدير'
            ], 403);
        }

        return $next($request);
    }
}
