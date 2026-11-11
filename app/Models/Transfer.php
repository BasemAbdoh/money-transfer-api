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
        'commission_amount',
        'net_amount',
        'currency',
        'company_type',
        'destination',
        'created_by',
        'user_id',
        'status',
        'notes',
        'internal_notes',
        'branch_id',
        'approval_status',
        'approved_by',
        'approved_at',
        'cancel_reason',
    ];

    protected $casts = [
        'approved_at'       => 'datetime',
        'amount'            => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'net_amount'        => 'decimal:2',
    ];

    // علاقة الحوالة بصاحبها
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // علاقة الحوالة بالفرع
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // علاقة الحوالة بالمعتمد
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // سجل تاريخ الحوالة
    public function historyLogs()
    {
        return $this->hasMany(TransferHistoryLog::class);
    }
}
