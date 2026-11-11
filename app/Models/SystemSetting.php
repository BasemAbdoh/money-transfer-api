<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    public static function getValue(string $key, $default = null)
    {
        try {
            if (!Schema::hasTable('system_settings')) {
                return $default;
            }
            $setting = self::where('key', $key)->first();
            return $setting !== null ? $setting->value : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public static function setValue(string $key, $value, ?string $description = null): self
    {
        $data = ['value' => (string) $value];
        if ($description !== null) {
            $data['description'] = $description;
        }
        return self::updateOrCreate(['key' => $key], $data);
    }
}

