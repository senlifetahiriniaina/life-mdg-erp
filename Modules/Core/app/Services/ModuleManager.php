<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\TenantModule;

class ModuleManager
{
    private const CACHE_TTL = 300; // 5 minutes

    /** Modules always required (cannot be disabled) */
    private const CORE_MODULES = ['Core'];

    /**
     * Return all modules enabled for the current tenant (optionally filtered by department).
     */
    public function enabledModules(?string $tenantId = null, ?string $department = null): Collection
    {
        $tenantId = $tenantId ?? $this->currentTenantId();
        $cacheKey = "modules:{$tenantId}:{$department}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenantId, $department) {
            $query = TenantModule::where('tenant_id', $tenantId)
                ->where('enabled', true);

            if ($department !== null) {
                $query->where(function ($q) use ($department) {
                    $q->where('department', $department)->orWhereNull('department');
                });
            }

            return $query->pluck('module')
                ->merge(self::CORE_MODULES)
                ->unique()
                ->values();
        });
    }

    /**
     * Check if a module is enabled for the current tenant.
     *
     * When no module records exist for this tenant (fresh install / unconfigured
     * tenant), all modules default to enabled so new tenants can explore the
     * full feature set before restricting access.
     */
    public function isEnabled(string $module, ?string $department = null): bool
    {
        if (in_array($module, self::CORE_MODULES)) {
            return true;
        }

        $tenantId = $this->currentTenantId();

        // Fresh install: no rows in tenant_modules at all → all modules are on by default.
        if (! TenantModule::where('tenant_id', $tenantId)->exists()) {
            return true;
        }

        return $this->enabledModules($tenantId, $department)->contains($module);
    }

    /**
     * Enable a module for a tenant (optionally scoped to a department).
     */
    public function enable(string $tenantId, string $module, ?string $department = null): void
    {
        TenantModule::updateOrCreate(
            ['tenant_id' => $tenantId, 'module' => $module, 'department' => $department],
            ['enabled' => true]
        );

        $this->clearCache($tenantId);
    }

    /**
     * Disable a module for a tenant.
     */
    public function disable(string $tenantId, string $module, ?string $department = null): void
    {
        if (in_array($module, self::CORE_MODULES)) {
            throw new \InvalidArgumentException("Module '{$module}' cannot be disabled.");
        }

        TenantModule::where([
            'tenant_id' => $tenantId,
            'module' => $module,
            'department' => $department,
        ])->update(['enabled' => false]);

        $this->clearCache($tenantId);
    }

    /**
     * Get the module settings for the current tenant.
     */
    public function settings(string $module, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?? $this->currentTenantId();
        $record = TenantModule::where('tenant_id', $tenantId)->where('module', $module)->first();

        return $record?->settings ?? [];
    }

    /**
     * Update module settings.
     */
    public function updateSettings(string $tenantId, string $module, array $settings): void
    {
        TenantModule::where(['tenant_id' => $tenantId, 'module' => $module])
            ->update(['settings' => array_merge($this->settings($module, $tenantId), $settings)]);

        $this->clearCache($tenantId);
    }

    private function clearCache(string $tenantId): void
    {
        // Delete all known department-scoped variants. We track possible departments
        // by querying unique departments for this tenant, plus the global (null) key.
        $departments = TenantModule::where('tenant_id', $tenantId)
            ->whereNotNull('department')
            ->distinct()
            ->pluck('department')
            ->prepend(null);

        foreach ($departments as $dept) {
            Cache::delete("modules:{$tenantId}:{$dept}");
        }
    }

    private function currentTenantId(): string
    {
        // Use tenancy tenant key when available (multi-tenant deployment),
        // fall back to the authenticated user's ID for single-tenant setups.
        return (string) (tenancy()->tenant?->getTenantKey() ?? auth()->id() ?? 'system');
    }
}
