<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPhoneConfirmed
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if ($user && !$user->is_active) {
            return response()->json([
                'error'        => 'حسابك غير مفعّل. يرجى تأكيد رقم جوالك أولاً',
                'phone_status' => $user->phone_status,
                'requires'     => $user->phone ? 'waiting_admin' : 'submit_phone',
            ], 403);
        }
        
        return $next($request);
    }
}
