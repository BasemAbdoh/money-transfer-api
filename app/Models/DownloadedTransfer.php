<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadedTransfer extends Model
{
    protected $fillable = [
        'trans_number',
        'downloaded_by',
        'user_id',
    ];
}
