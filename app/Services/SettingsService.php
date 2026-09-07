<?php

namespace App\Services;

use App\Models\Setting;
use InvalidArgumentException;

/**
 * DB-backed overrides for a small, deliberately fixed set of admin-tunable
 * knobs — not a generic feature-flag system. Every other config('cdh.*')
 * value (backup file paths, the encryption key, upload_chunk_max_bytes)
 * stays env-only; these four are the ones an admin can change without a
 * deploy. A key with no row falls back to its env-backed config default, so
 * `settings` only ever holds the keys someone has actually changed.
 */
class SettingsService
{
    /**
     * Managed key => its config('cdh.*') fallback path.
     *
     * @var array<string, string>
     */
    private const KEYS = [
        'image_retention_months' => 'cdh.image_retention_months',
        'image_max_bytes' => 'cdh.image_max_bytes',
        'upload_session_ttl_hours' => 'cdh.upload_session_ttl_hours',
        'backup_retention_days' => 'cdh.backup.retention_days',
    ];

    public function get(string $key): int
    {
        $this->assertKnownKey($key);

        $value = Setting::query()->find($key)?->value;

        return $value !== null ? (int) $value : (int) config(self::KEYS[$key]);
    }

    public function set(string $key, int $value): void
    {
        $this->assertKnownKey($key);

        Setting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }

    /**
     * Every managed key mapped to its currently effective value (DB override
     * if one exists, otherwise the config default) — what the settings form
     * displays.
     *
     * @return array<string, int>
     */
    public function all(): array
    {
        return collect(array_keys(self::KEYS))
            ->mapWithKeys(fn (string $key): array => [$key => $this->get($key)])
            ->all();
    }

    private function assertKnownKey(string $key): void
    {
        if (! array_key_exists($key, self::KEYS)) {
            throw new InvalidArgumentException("Unknown setting [{$key}].");
        }
    }
}
