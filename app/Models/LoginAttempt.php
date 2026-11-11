<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $fillable = [
        'identifier',
        'ip_address',
        'success',
        'attempted_at',
    ];

    protected $casts = [
        'success'      => 'boolean',
        'attempted_at' => 'datetime',
    ];

    // جلب المحاولات الفاشلة الحديثة
    public static function getRecentFailures(string $identifier, int $minutes = 15): int
    {
        return static::where('identifier', $identifier)
            ->where('success', false)
            ->where('attempted_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    // هل الحساب محظور؟
    public static function isBlocked(string $identifier, int $maxAttempts = 5, int $minutes = 15): bool
    {
        return static::getRecentFailures($identifier, $minutes) >= $maxAttempts;
    }
}
