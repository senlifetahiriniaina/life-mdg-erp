<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Jobs\ProvisionTenantJob;

/**
 * Manages the full lifecycle of a tenant after initial registration:
 * plan changes, module toggles, suspension, activation, re-provisioning.
 *
 * This service operates on the central database (not tenant databases).
 */
class TenantProvisioningService
{
    /** Plans available and their module allowlists (null = all modules allowed). */
    private const PLAN_MODULES = [
        'starter'    => ['Core', 'CRM', 'HR', 'Inventory'],
        'growth'     => ['Core', 'CRM', 'HR', 'Inventory', 'Accounting', 'POS', 'Ecommerce', 'Email', 'Helpdesk', 'Projects'],
        'enterprise' => null, // all modules
    ];

    /** All available modules. */
    private const ALL_MODULES = [
        'Core', 'CRM', 'HR', 'Inventory', 'Accounting',
        'Manufacturing', 'POS', 'Ecommerce', 'BI', 'Email',
        'Documents', 'Helpdesk', 'Projects', 'WhatsApp',
    ];

    // ── Queries ────────────────────────────────────────────────────────────────

    /**
     * Return paginated list of all tenants with module counts.
     */
    public function list(int $perPage = 25, ?string $search = null, ?string $plan = null): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = DB::table('tenants')
            ->select(
                'tenants.id',
                'tenants.slug',
                'tenants.company_name',
                'tenants.domain',
                'tenants.plan',
                'tenants.is_active',
                'tenants.created_at',
                DB::raw('(SELECT COUNT(*) FROM tenant_modules WHERE tenant_modules.tenant_id = tenants.id AND tenant_modules.enabled = 1) as enabled_modules_count')
            );

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%");
            });
        }

        if ($plan) {
            $query->where('plan', $plan);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * Return a single tenant with its enabled modules.
     */
    public function find(string $tenantId): ?object
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        if (!$tenant) {
            return null;
        }

        $tenant->modules = DB::table('tenant_modules')
            ->where('tenant_id', $tenantId)
            ->orderBy('module')
            ->get();

        return $tenant;
    }

    // ── Lifecycle ──────────────────────────────────────────────────────────────

    /**
     * Update basic tenant attributes (company name, plan, domain).
     * Changing plan adjusts the allowed module set automatically.
     */
    public function update(string $tenantId, array $data): void
    {
        $allowed = ['company_name', 'plan', 'domain', 'is_active'];
        $payload = array_intersect_key($data, array_flip($allowed));
        $payload['updated_at'] = now();

        DB::table('tenants')->where('id', $tenantId)->update($payload);

        // When downgrading plan, disable modules not in the new plan's allowlist
        if (isset($payload['plan'])) {
            $this->enforceModulesForPlan($tenantId, $payload['plan']);
        }
    }

    /**
     * Suspend a tenant — marks is_active = false, does NOT delete data.
     */
    public function suspend(string $tenantId): void
    {
        DB::table('tenants')
            ->where('id', $tenantId)
            ->update(['is_active' => false, 'updated_at' => now()]);

        Log::info("Tenant suspended", ['tenant_id' => $tenantId]);
    }

    /**
     * Reactivate a previously suspended tenant.
     */
    public function activate(string $tenantId): void
    {
        DB::table('tenants')
            ->where('id', $tenantId)
            ->update(['is_active' => true, 'updated_at' => now()]);

        Log::info("Tenant activated", ['tenant_id' => $tenantId]);
    }

    /**
     * Re-dispatch ProvisionTenantJob to run pending migrations and seeds.
     * Safe to run on an already-provisioned tenant (migrations are idempotent).
     */
    public function reprovision(string $tenantId): void
    {
        Log::info("Reprovisioning tenant", ['tenant_id' => $tenantId]);
        ProvisionTenantJob::dispatch($tenantId);
    }

    /**
     * Permanently delete a tenant and all central-DB references.
     * The tenant's own database must be dropped separately via the job.
     */
    public function delete(string $tenantId): void
    {
        DB::transaction(function () use ($tenantId) {
            DB::table('tenant_modules')->where('tenant_id', $tenantId)->delete();
            DB::table('tenants')->where('id', $tenantId)->delete();
        });

        Log::warning("Tenant deleted from central DB", ['tenant_id' => $tenantId]);
    }

    // ── Modules ───────────────────────────────────────────────────────────────

    /**
     * Return the enabled/disabled state of every module for a tenant.
     *
     * @return array<string, bool>
     */
    public function getModules(string $tenantId): array
    {
        $rows = DB::table('tenant_modules')
            ->where('tenant_id', $tenantId)
            ->pluck('enabled', 'module')
            ->all();

        // Include all known modules even if not yet in tenant_modules
        $result = [];
        foreach (self::ALL_MODULES as $module) {
            $result[$module] = (bool) ($rows[$module] ?? false);
        }

        return $result;
    }

    /**
     * Bulk-set enabled/disabled state for a list of modules.
     *
     * @param array<string, bool> $modules  ['CRM' => true, 'Manufacturing' => false, ...]
     */
    public function setModules(string $tenantId, array $modules): void
    {
        // Validate requested modules against plan allowlist
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        $planAllowlist = self::PLAN_MODULES[$tenant?->plan ?? 'enterprise'] ?? null;

        $now = now();

        foreach ($modules as $module => $enabled) {
            if (!in_array($module, self::ALL_MODULES, true)) {
                continue; // skip unknown modules
            }

            // Prevent enabling modules outside the plan's allowlist
            if ($enabled && $planAllowlist !== null && !in_array($module, $planAllowlist, true)) {
                continue;
            }

            DB::table('tenant_modules')->updateOrInsert(
                ['tenant_id' => $tenantId, 'module' => $module],
                ['enabled' => $enabled, 'updated_at' => $now]
            );
        }
    }

    // ── Tenant stats ──────────────────────────────────────────────────────────

    /**
     * Return basic usage statistics for a tenant.
     */
    public function stats(string $tenantId): array
    {
        $enabledModules = DB::table('tenant_modules')
            ->where('tenant_id', $tenantId)
            ->where('enabled', true)
            ->count();

        return [
            'tenant_id'      => $tenantId,
            'enabled_modules' => $enabledModules,
            'total_modules'   => count(self::ALL_MODULES),
            'available_plans' => array_keys(self::PLAN_MODULES),
        ];
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function enforceModulesForPlan(string $tenantId, string $plan): void
    {
        $allowlist = self::PLAN_MODULES[$plan] ?? null;

        // 'enterprise' plan has no restrictions
        if ($allowlist === null) {
            return;
        }

        // Disable any currently-enabled modules that are not in the new plan
        DB::table('tenant_modules')
            ->where('tenant_id', $tenantId)
            ->where('enabled', true)
            ->whereNotIn('module', $allowlist)
            ->update(['enabled' => false, 'updated_at' => now()]);
    }
}
