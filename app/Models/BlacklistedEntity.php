<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class BlacklistedEntity extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'value',
        'reason',
        'added_by',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * التحقق مما إذا كان اسم المستلم أو رقم الحساب أو الهاتف مدرجاً في القائمة السوداء النشطة
     */
    public static function checkTransfer(?string $receiverName, ?string $accountNumber): ?self
    {
        try {
            if (!Schema::hasTable('blacklisted_entities')) {
                return null;
            }

            $activeItems = self::where('is_active', true)->get();
            foreach ($activeItems as $item) {
                $val = mb_strtolower(trim($item->value));
                if ($val === '') {
                    continue;
                }

                if ($item->type === 'name' && $receiverName) {
                    if (mb_strpos(mb_strtolower(trim($receiverName)), $val) !== false) {
                        return $item;
                    }
                } elseif (in_array($item->type, ['account_number', 'phone'], true) && $accountNumber) {
                    if (trim($accountNumber) === $val) {
                        return $item;
                    }
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}

