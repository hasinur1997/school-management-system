<?php

namespace App\Settings;

/**
 * The closed registry of known setting keys. Each key declares its scope
 * (global vs per-branch), its value type for input validation, and whether it
 * is a write-only secret (never returned by GET). Unknown keys are rejected at
 * the boundary (422). Types: string, int, bool, time (HH:MM).
 */
class SettingRegistry
{
    public const GLOBAL = 'global';

    public const BRANCH = 'branch';

    /**
     * @var array<string, array<string, mixed>>
     */
    public const KEYS = [
        'school_name' => ['scope' => self::GLOBAL, 'type' => 'string', 'secret' => false],
        'school_logo' => ['scope' => self::GLOBAL, 'type' => 'string', 'secret' => false],
        'current_session_id' => ['scope' => self::GLOBAL, 'type' => 'int', 'secret' => false],
        'sslcommerz_store_id' => ['scope' => self::GLOBAL, 'type' => 'string', 'secret' => false],
        'sslcommerz_store_password' => ['scope' => self::GLOBAL, 'type' => 'string', 'secret' => true],
        'sslcommerz_sandbox' => ['scope' => self::GLOBAL, 'type' => 'bool', 'secret' => false],
        'mail_from' => ['scope' => self::GLOBAL, 'type' => 'string', 'secret' => false],
        'sms_api_key' => ['scope' => self::GLOBAL, 'type' => 'string', 'secret' => true],
        'partial_payment_enabled' => ['scope' => self::BRANCH, 'type' => 'bool', 'secret' => false],
        'late_fee_enabled' => ['scope' => self::BRANCH, 'type' => 'bool', 'secret' => false],
        'teacher_late_threshold' => ['scope' => self::BRANCH, 'type' => 'time', 'secret' => false],
        'invoice_due_day' => ['scope' => self::BRANCH, 'type' => 'int', 'secret' => false, 'min' => 1, 'max' => 28],
    ];

    /**
     * Whether the key is part of the registry.
     */
    public static function has(string $key): bool
    {
        return array_key_exists($key, self::KEYS);
    }

    /**
     * Whether the key is a global (non-branch) setting.
     */
    public static function isGlobal(string $key): bool
    {
        return (self::KEYS[$key]['scope'] ?? null) === self::GLOBAL;
    }

    /**
     * Whether the key is a write-only secret, masked on read.
     */
    public static function isSecret(string $key): bool
    {
        return (bool) (self::KEYS[$key]['secret'] ?? false);
    }

    /**
     * @return array<int, string>
     */
    public static function globalKeys(): array
    {
        return array_keys(array_filter(self::KEYS, fn (array $meta): bool => $meta['scope'] === self::GLOBAL));
    }

    /**
     * @return array<int, string>
     */
    public static function branchKeys(): array
    {
        return array_keys(array_filter(self::KEYS, fn (array $meta): bool => $meta['scope'] === self::BRANCH));
    }

    /**
     * Validate a value against its key's declared type. Returns an error
     * message when the value does not match, or null when it is acceptable.
     */
    public static function validate(string $key, mixed $value): ?string
    {
        $meta = self::KEYS[$key];

        return match ($meta['type']) {
            'string' => is_string($value) ? null : 'Must be a string',
            'bool' => is_bool($value) ? null : 'Must be a boolean',
            'time' => is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1
                ? null
                : 'Must be a time in HH:MM format',
            'int' => self::validateInt($value, $meta),
            default => 'Unsupported type',
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function validateInt(mixed $value, array $meta): ?string
    {
        if (! is_int($value)) {
            return 'Must be an integer';
        }

        if (isset($meta['min']) && $value < $meta['min']) {
            return "Must be at least {$meta['min']}";
        }

        if (isset($meta['max']) && $value > $meta['max']) {
            return "Must be at most {$meta['max']}";
        }

        return null;
    }
}
