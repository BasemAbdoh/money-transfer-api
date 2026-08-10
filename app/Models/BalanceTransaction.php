<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BalanceTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'description',
        'trans_number',
        'balance_before',
        'balance_after',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
