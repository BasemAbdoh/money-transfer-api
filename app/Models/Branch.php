<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['name', 'city', 'balance', 'credit_limit', 'is_active'];

    protected $casts = [
        'balance'      => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'is_active'    => 'boolean',
    ];

    // علاقة الفرع بالمستخدمين
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // علاقة الفرع بالحوالات
    public function transfers()
    {
        return $this->hasMany(Transfer::class);
    }
}
