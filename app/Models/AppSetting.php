<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'value',
])]
class AppSetting extends Model
{
    public const PERMESSO_SOGGIORNO_ALERT_DAYS = 'permesso_soggiorno_alert_days';

    public const VISITA_MEDICA_ALERT_DAYS = 'visita_medica_alert_days';

    public const MANSIONI = 'mansioni';

    public const DEFAULTS = [
        self::PERMESSO_SOGGIORNO_ALERT_DAYS => 90,
        self::VISITA_MEDICA_ALERT_DAYS => 60,
        self::MANSIONI => [
            'Educatore',
            'OSS',
            'ASA',
            'Addetto pulizie',
            'Cuoco',
            'Autista',
            'Impiegato amministrativo',
        ],
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function getValue(string $key): mixed
    {
        return static::query()->where('key', $key)->first()?->value ?? static::DEFAULTS[$key] ?? null;
    }

    public static function int(string $key): int
    {
        return max(0, (int) static::getValue($key));
    }

    /**
     * @return array<int, string>
     */
    public static function mansioni(): array
    {
        $mansioni = static::getValue(static::MANSIONI);

        if (! is_array($mansioni)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $mansione): string => trim((string) $mansione),
            $mansioni,
        )));
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
