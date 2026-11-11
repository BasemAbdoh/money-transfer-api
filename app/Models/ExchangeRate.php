<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'currency_code',
        'currency_name',
        'buy_rate',
        'sell_rate',
        'updated_by',
        'is_active',
    ];

    protected $casts = [
        'buy_rate'  => 'decimal:4',
        'sell_rate' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

