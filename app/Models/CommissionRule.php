<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    protected $fillable = [
        'company_type',
        'currency',
        'min_amount',
        'max_amount',
        'fee_type',
        'fee_value',
        'is_active',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'fee_value'  => 'decimal:4',
        'is_active'  => 'boolean',
    ];

    /**
     * حساب العمولة تلقائياً حسب الشركة والعملة والمبلغ
     */
    public static function calculateFee(?string $companyType, ?string $currency, float $amount): float
    {
        $rules = static::where('is_active', true)
            ->where('min_amount', '<=', $amount)
            ->where(function ($q) use ($amount) {
                $q->whereNull('max_amount')
                  ->orWhere('max_amount', '>=', $amount);
            })
            ->get();

        // إعطاء الأولوية للقاعدة المخصصة للشركة والعملة معاً، ثم للشركة، ثم للعملة، ثم العامة
        $matched = $rules->sortByDesc(function ($rule) use ($companyType, $currency) {
            $score = 0;
            if ($rule->company_type && $rule->company_type === $companyType) {
                $score += 2;
            } elseif ($rule->company_type && $rule->company_type !== $companyType) {
                return -1;
            }
            if ($rule->currency && $rule->currency === $currency) {
                $score += 1;
            } elseif ($rule->currency && $rule->currency !== $currency) {
                return -1;
            }
            return $score;
        })->first(function ($rule) use ($companyType, $currency) {
            $companyOk = is_null($rule->company_type) || $rule->company_type === $companyType;
            $currencyOk = is_null($rule->currency) || $rule->currency === $currency;
            return $companyOk && $currencyOk;
        });

        if (!$matched) {
            return 0.0;
        }

        if ($matched->fee_type === 'fixed') {
            return round((float) $matched->fee_value, 2);
        }

        return round(($amount * (float) $matched->fee_value) / 100, 2);
    }
}

