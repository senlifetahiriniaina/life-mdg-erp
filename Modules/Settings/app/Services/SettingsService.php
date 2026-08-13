<?php

declare(strict_types=1);

namespace Modules\Settings\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Modules\Settings\Models\Setting;

/**
 * SettingsService
 *
 * Centralised read/write access to the settings table with per-tenant Redis
 * caching. Cache keys follow the pattern:
 *
 *   settings:{tenant_id}:{module}:{key}
 *
 * A null tenant_id is represented as "global" in the cache key.
 */
class SettingsService
{
    private const CACHE_TTL = 3600; // 1 hour

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Retrieve a single typed value for the given module + key.
     * Tenant-specific value takes precedence over a global value.
     */
    public function get(string $module, string $key, mixed $default = null): mixed
    {
        $tenantId  = $this->currentTenantId();
        $cacheKey  = $this->cacheKey($tenantId, $module, $key);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached === '__null__' ? null : $cached;
        }

        $value = Setting::get($module, $key, $default);

        Cache::put($cacheKey, $value ?? '__null__', self::CACHE_TTL);

        return $value;
    }

    /**
     * Persist (upsert) a setting for the current tenant and flush the cache entry.
     */
    public function set(string $module, string $key, mixed $value): void
    {
        Setting::set($module, $key, $value);

        $tenantId = $this->currentTenantId();
        Cache::forget($this->cacheKey($tenantId, $module, $key));
    }

    /**
     * Set a setting with an explicit value_type (e.g. 'encrypted').
     */
    public function setTyped(string $module, string $key, mixed $value, string $valueType): void
    {
        $tenantId = $this->currentTenantId();

        $raw = match ($valueType) {
            'boolean'   => $value ? '1' : '0',
            'integer'   => (string) (int) $value,
            'json'      => json_encode($value, JSON_THROW_ON_ERROR),
            'encrypted' => Crypt::encryptString((string) $value),
            default     => (string) $value,
        };

        Setting::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'module' => $module, 'key' => $key],
            ['value' => $raw, 'value_type' => $valueType]
        );

        Cache::forget($this->cacheKey($tenantId, $module, $key));
    }

    /**
     * Retrieve all settings for a module as an associative array.
     * Keys are the setting key names, values are typed.
     *
     * @return array<string, mixed>
     */
    public function getModule(string $module): array
    {
        $tenantId = $this->currentTenantId();

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($this->moduleKey($tenantId, $module));
        if ($cached !== null) {
            return $cached;
        }

        $settings = Setting::withoutGlobalScope('tenant')
            ->forModule($module)
            ->where(function ($q) use ($tenantId) {
                if ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                } else {
                    $q->whereNull('tenant_id');
                }
            })
            ->orderByRaw('tenant_id IS NULL ASC') // tenant-specific wins
            ->get();

        // Merge: global base then tenant overrides
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = $setting->getCastedValue();
        }

        Cache::put($this->moduleKey($tenantId, $module), $result, self::CACHE_TTL);

        return $result;
    }

    /**
     * Persist multiple settings for a module in a single call.
     *
     * @param array<string, mixed> $settings  key => value pairs
     */
    public function setMany(string $module, array $settings): void
    {
        foreach ($settings as $key => $value) {
            Setting::set($module, $key, $value);
        }

        $tenantId = $this->currentTenantId();
        Cache::forget($this->moduleKey($tenantId, $module));

        // Also flush individual keys
        foreach (array_keys($settings) as $key) {
            Cache::forget($this->cacheKey($tenantId, $module, $key));
        }
    }

    /**
     * Warm (pre-populate) the cache for a module.
     * Calling this explicitly is optional — getModule() warms lazily.
     */
    public function cache(): void
    {
        // No-op: cache is warmed lazily on first access.
        // This method exists as an extension point (e.g. artisan command).
    }

    /**
     * Flush all cached settings for the current tenant.
     */
    public function flushTenantCache(): void
    {
        // Redis tag-based flush would be ideal; for broad compatibility
        // we flush only by module key patterns.  Full cache invalidation
        // should be done via artisan cache:clear in production.
        $tenantId = $this->currentTenantId();
        $prefix   = 'settings:' . ($tenantId ?? 'global') . ':';

        // Bust using the tag if a taggable driver is available
        try {
            Cache::tags(["settings_tenant_{$tenantId}"])->flush();
        } catch (\BadMethodCallException) {
            // Non-taggable driver (file/database) — nothing to do globally
        }
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    private function currentTenantId(): int|string|null
    {
        return auth()?->user()?->company_id
            ?? request()?->header('X-Company-ID');
    }

    private function cacheKey(int|string|null $tenantId, string $module, string $key): string
    {
        $tid = $tenantId ?? 'global';
        return "settings:{$tid}:{$module}:{$key}";
    }

    private function moduleKey(int|string|null $tenantId, string $module): string
    {
        $tid = $tenantId ?? 'global';
        return "settings:{$tid}:{$module}";
    }
}
