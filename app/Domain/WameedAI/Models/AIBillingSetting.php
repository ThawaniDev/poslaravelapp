<?php

namespace App\Domain\WameedAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AIBillingSetting extends Model
{
    use HasUuids;

    protected $table = 'ai_billing_settings';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function getFloat(string $key, float $default = 0): float
    {
        return (float) static::getValue($key, $default);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int) static::getValue($key, $default);
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::getValue($key);
        if ($value === null) return $default;
        return in_array(strtolower((string) $value), ['true', '1', 'yes'], true);
    }

    public static function setValue(string $key, mixed $value, ?string $description = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            array_filter([
                'value' => (string) $value,
                'description' => $description,
            ], fn ($v) => $v !== null),
        );
    }

    public static function getAllSettings(): array
    {
        return static::all()->pluck('value', 'key')->toArray();
    }
}
