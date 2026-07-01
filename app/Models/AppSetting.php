<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

#[Fillable([
    'key',
    'value',
])]
class AppSetting extends Model
{
    public const PERMESSO_SOGGIORNO_ALERT_DAYS = 'permesso_soggiorno_alert_days';

    public const VISITA_MEDICA_ALERT_DAYS = 'visita_medica_alert_days';

    public const MANSIONI = 'mansioni';

    public const S3_ARCHIVE_ENABLED = 's3_archive_enabled';

    public const S3_ACCESS_KEY_ID = 's3_access_key_id';

    public const S3_SECRET_ACCESS_KEY = 's3_secret_access_key';

    public const S3_DEFAULT_REGION = 's3_default_region';

    public const S3_BUCKET = 's3_bucket';

    public const S3_ENDPOINT = 's3_endpoint';

    public const S3_USE_PATH_STYLE_ENDPOINT = 's3_use_path_style_endpoint';

    public const SMTP_HOST = 'smtp_host';

    public const SMTP_PORT = 'smtp_port';

    public const SMTP_SCHEME = 'smtp_scheme';

    public const SMTP_USERNAME = 'smtp_username';

    public const SMTP_PASSWORD = 'smtp_password';

    public const SMTP_FROM_ADDRESS = 'smtp_from_address';

    public const SMTP_FROM_NAME = 'smtp_from_name';

    public const MAIL_PROVIDER = 'mail_provider';

    public const TRADITIONAL_SMTP_HOST = 'traditional_smtp_host';

    public const TRADITIONAL_SMTP_PORT = 'traditional_smtp_port';

    public const TRADITIONAL_SMTP_SCHEME = 'traditional_smtp_scheme';

    public const TRADITIONAL_SMTP_USERNAME = 'traditional_smtp_username';

    public const TRADITIONAL_SMTP_PASSWORD = 'traditional_smtp_password';

    public const TRADITIONAL_SMTP_FROM_ADDRESS = 'traditional_smtp_from_address';

    public const TRADITIONAL_SMTP_FROM_NAME = 'traditional_smtp_from_name';

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
        self::S3_ARCHIVE_ENABLED => false,
        self::S3_ACCESS_KEY_ID => null,
        self::S3_SECRET_ACCESS_KEY => null,
        self::S3_DEFAULT_REGION => 'eu-south-1',
        self::S3_BUCKET => null,
        self::S3_ENDPOINT => null,
        self::S3_USE_PATH_STYLE_ENDPOINT => false,
        self::SMTP_HOST => null,
        self::SMTP_PORT => 587,
        self::SMTP_SCHEME => 'smtp',
        self::SMTP_USERNAME => null,
        self::SMTP_PASSWORD => null,
        self::SMTP_FROM_ADDRESS => null,
        self::SMTP_FROM_NAME => 'Libro Soci COOP',
        self::MAIL_PROVIDER => 'ses',
        self::TRADITIONAL_SMTP_HOST => null,
        self::TRADITIONAL_SMTP_PORT => 587,
        self::TRADITIONAL_SMTP_SCHEME => 'smtp',
        self::TRADITIONAL_SMTP_USERNAME => null,
        self::TRADITIONAL_SMTP_PASSWORD => null,
        self::TRADITIONAL_SMTP_FROM_ADDRESS => null,
        self::TRADITIONAL_SMTP_FROM_NAME => 'Libro Soci COOP',
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

    public static function bool(string $key): bool
    {
        return (bool) static::getValue($key);
    }

    public static function string(string $key): ?string
    {
        $value = static::getValue($key);

        if (blank($value)) {
            return null;
        }

        return (string) $value;
    }

    public static function s3SecretAccessKey(): ?string
    {
        $encrypted = static::string(static::S3_SECRET_ACCESS_KEY);

        if (blank($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
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

    public static function setS3SecretAccessKey(?string $value): void
    {
        if (blank($value)) {
            return;
        }

        static::setValue(static::S3_SECRET_ACCESS_KEY, Crypt::encryptString($value));
    }

    public static function smtpPassword(): ?string
    {
        $encrypted = static::string(static::SMTP_PASSWORD);

        if (blank($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function setSmtpPassword(?string $value): void
    {
        if (filled($value)) {
            static::setValue(static::SMTP_PASSWORD, Crypt::encryptString($value));
        }
    }

    public static function traditionalSmtpPassword(): ?string
    {
        $encrypted = static::string(static::TRADITIONAL_SMTP_PASSWORD);

        if (blank($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function setTraditionalSmtpPassword(?string $value): void
    {
        if (filled($value)) {
            static::setValue(static::TRADITIONAL_SMTP_PASSWORD, Crypt::encryptString($value));
        }
    }
}
