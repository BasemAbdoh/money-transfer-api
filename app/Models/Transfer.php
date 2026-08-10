<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $fillable = [
        'trans_number',
        'account_number',
        'receiver_name',
        'receiver_email',
        'amount',
        'currency',
        'company_type',
        'destination',
        'created_by',
        'user_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
