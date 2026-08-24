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
     *
     * Chantier 32.9 (14-layer deep audit, layer 4/6): this only ever forgot
     * the single-key cache entry, never the module-level `getModule()`
     * cache — confirmed empirically via tinker: priming `getModule($module)`
     * then calling `set()` on one of that module's keys left the cached
     * module snapshot stale for up to CACHE_TTL (1h), so any real caller
     * hitting `SettingsController::showModule()` right after a real
     * `update()`/`bulk()` write could see the pre-write value for an hour.
     * Fixed to also forget the module-level key, matching what setMany()
     * already correctly does.
     */
    public function set(string $module, string $key, mixed $value): void
    {
        Setting::set($module, $key, $value);

        $tenantId = $this->currentTenantId();
        Cache::forget($this->cacheKey($tenantId, $module, $key));
        Cache::forget($this->moduleKey($tenantId, $module));
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

        // Chantier 32.9: same module-level staleness bug as set() above,
        // fixed alongside it.
        Cache::forget($this->moduleKey($tenantId, $module));

        Cache::forget($this->cacheKey($tenantId, $module, $key));
    }

    /**
     * Retrieve all settings for a module as an associative array.
     * Keys are the setting key names, values are typed.
     *
     * Chantier 32.9 (14-layer deep audit, layer 4 — real bug found by
     * execution, not by reading): the query orders tenant-specific rows
     * first (`tenant_id IS NULL ASC` — correct, and what Setting::get()'s
     * own `->first()` correctly relies on), but this method's merge loop
     * used plain `$result[$key] = ...` overwrite semantics — since PHP
     * array assignment always takes the LAST write, and global rows are
     * ordered LAST, every global default silently overwrote its own
     * tenant-specific override instead of the reverse. Confirmed
     * empirically via tinker: a real global `theme=global-theme` plus a
     * real tenant override `theme=tenant-theme` for the SAME tenant
     * produced `getModule()` => `theme: 'global-theme'` — the tenant's own
     * override was invisible — while `Setting::get()` on the identical
     * data correctly returned `'tenant-theme'`. The pre-existing code
     * comment ("tenant-specific wins") already documented the *intended*
     * behavior; the code just never implemented it. Fixed with `??=` so
     * only the first (tenant-specific-first, per the ORDER BY) occurrence
     * of each key is kept.
     *
     * Chantier 32.9 (14-layer deep audit, layer 6 — security, secrets/PII):
     * SettingPolicy::view()'s own docblock/comment on
     * SettingsController::showModule() explicitly describes non-public
     * settings as gated by `view()`/belongsToTenant() — but this method
     * never actually applied that per-record check, so any caller who
     * clears the (deliberately permissive-by-design) class-level
     * `viewAny()` ability got EVERY setting for a module, `is_public` or
     * not, with an `encrypted` value_type setting returned fully
     * decrypted. Confirmed empirically that a plain employee with no
     * `settings.view` permission (a real, if currently unseeded-by-default,
     * scenario — every route-gated role in this app's seeder happens to
     * carry `settings.view` today, but nothing in the code enforced it)
     * would see a decrypted `encrypted`-type "secret" value regardless.
     * Fixed by filtering non-public entries to callers who hold
     * `settings.view` — matching SettingPolicy::view()'s own rule, minus
     * the tenant-ownership half (getModule()'s query already scopes to
     * the caller's own tenant). The underlying per-tenant cache still
     * stores the FULL unfiltered snapshot (with each entry's own
     * `is_public` flag) rather than a pre-filtered one, and the visibility
     * filter is re-applied on every call after the cache read — so a
     * privileged caller priming the cache can never leak a private value
     * to a later, less-privileged caller sharing the same cache key.
     *
     * @return array<string, mixed>
     */
    public function getModule(string $module): array
    {
        $tenantId = $this->currentTenantId();
        $cacheKey = $this->moduleKey($tenantId, $module);

        /** @var array<string, array{value: mixed, is_public: bool}>|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached === null) {
            $settings = Setting::withoutGlobalScope('tenant')
                ->forModule($module)
                ->where(function ($q) use ($tenantId) {
                    if ($tenantId) {
                        $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                    } else {
                        $q->whereNull('tenant_id');
                    }
                })
                ->orderByRaw('tenant_id IS NULL ASC') // tenant-specific first
                ->get();

            // Merge: tenant-specific override wins, global is only the
            // fallback for a key the tenant hasn't overridden.
            $cached = [];
            foreach ($settings as $setting) {
                $cached[$setting->key] ??= [
                    'value'     => $setting->getCastedValue(),
                    'is_public' => (bool) $setting->is_public,
                ];
            }

            Cache::put($cacheKey, $cached, self::CACHE_TTL);
        }

        $canViewPrivate = (bool) auth()?->user()?->can('settings.view');

        $result = [];
        foreach ($cached as $key => $entry) {
            if ($entry['is_public'] || $canViewPrivate) {
                $result[$key] = $entry['value'];
            }
        }

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

    /**
     * Chantier 19 Lot 3: dropped the client-controlled X-Company-ID header
     * fallback — the same real cross-tenant IDOR already fixed for Setup's
     * identical pattern in Chantier 8.5sv (see Modules\Settings\Models\
     * Setting::boot()'s docblock for the full rationale; this method had 3
     * sibling instances of the identical bug, all fixed in the same pass).
     * bulk()'s SettingsController::authorize('create', Setting::class) call
     * is a class-level ability with no per-record tenant check at all — it
     * is this method (called by set()/setTyped()/getModule() underneath
     * it) that was the only real tenant boundary for that write path, so
     * this was the more severe of the header-fallback instances.
     */
    private function currentTenantId(): int|string|null
    {
        return auth()?->user()?->company_id;
    }

    private function cacheKey(int|string|null $tenantId, string $module, string $key): string
    {
        $tid = $tenantId ?? 'global';
        return "settings:{$tid}:{$module}:{$key}";
    }

    private function moduleKey(int|string|null $tenantId, string $module): string
    {
        $tid = $tenantId ?? 'global';
        // Chantier 32.9: bumped to a ":v2" suffix when the cached shape
        // changed from a flat `key => value` map to `key => {value,
        // is_public}` (needed for the new per-record visibility filter
        // below) — guarantees a rolling deploy never misreads a
        // pre-existing flat-shaped cache entry as the new shape (which
        // would fatal on `$entry['is_public']` against a scalar).
        return "settings:{$tid}:{$module}:v2";
    }
}
