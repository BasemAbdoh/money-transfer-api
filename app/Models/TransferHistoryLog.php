<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferHistoryLog extends Model
{
    protected $fillable = [
        'transfer_id', 'user_id', 'action', 'description', 'old_data', 'new_data',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
