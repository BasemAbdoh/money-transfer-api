<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'balance',
        'role',
        'profileImage',
        'fcm_token',
        'user_number',
        'email_otp',
        'phone',
        'phone_status',
        'is_active',
        'parent_user_id',
        'email_verified_at',
        'branch_id',
        'daily_transfer_limit',
        'daily_transfer_count_limit',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'daily_transfer_limit' => 'decimal:2',
        ];
    }

    // ==================== العلاقات ====================

    // علاقة المستخدم بالفرع
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // حوالات المستخدم
    public function transfers()
    {
        return $this->hasMany(Transfer::class);
    }

    // إشعارات المستخدم الداخلية
    public function inAppNotifications()
    {
        return $this->hasMany(InAppNotification::class);
    }

    // ==================== مساعدات الصلاحيات ====================

    public function isAdmin(): bool
    {
        return $this->role === 'Admin';
    }

    public function isSupervisor(): bool
    {
        return $this->role === 'Supervisor';
    }

    public function isUser(): bool
    {
        return $this->role === 'User';
    }

    // ==================== التحقق من الحد اليومي ====================

    // حساب مجموع الحوالات المرسلة اليوم
    public function todayTransfersAmount(): float
    {
        return (float) $this->transfers()
            ->whereDate('created_at', today())
            ->whereNotIn('status', ['cancelled'])
            ->sum('amount');
    }

    // عدد الحوالات المرسلة اليوم
    public function todayTransfersCount(): int
    {
        return $this->transfers()
            ->whereDate('created_at', today())
            ->whereNotIn('status', ['cancelled'])
            ->count();
    }

    // هل تجاوز الحد اليومي للمبلغ؟
    public function hasExceededDailyAmountLimit(float $amount): bool
    {
        if ($this->daily_transfer_limit <= 0) return false;
        return ($this->todayTransfersAmount() + $amount) > $this->daily_transfer_limit;
    }

    // هل تجاوز الحد اليومي للعدد؟
    public function hasExceededDailyCountLimit(): bool
    {
        if ($this->daily_transfer_count_limit <= 0) return false;
        return $this->todayTransfersCount() >= $this->daily_transfer_count_limit;
    }
}
