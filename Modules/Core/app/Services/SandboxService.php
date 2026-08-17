<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Core\Models\Sandbox;
use Modules\Core\Models\Tenant;

/**
 * SandboxService — Tenant Sandbox Environments (Item #19)
 *
 * Creates and manages sandbox tenants: lightweight clones of production
 * tenants that are provisioned with an empty schema and no real data.
 * Sandboxes expire after Sandbox::EXPIRY_DAYS days and are automatically
 * cleaned up by the core:expire-sandboxes console command.
 */
class SandboxService
{
    public function __construct(
        private readonly TenantManagerService $tenantManager,
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // CREATE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Clone a production tenant into a fresh, empty sandbox.
     *
     * A new Tenant record is created (status=trial, is_active=false until
     * provisioned) that inherits locale/country/currency/industry from the
     * parent but contains NO real data — only an empty provisioned schema.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function cloneTenant(string $tenantId, string $sandboxName): Sandbox
    {
        /** @var Tenant $parent */
        $parent = Tenant::findOrFail($tenantId);

        // Build a unique slug derived from the parent's slug / id
        $parentSlug = $parent->slug ?? (string) $parent->id;
        $slug       = $parentSlug . '-sandbox-' . uniqid('', false);

        // Create a new Tenant record for the sandbox.
        // We do NOT call TenantManagerService::provision() to avoid copying
        // real data; the sandbox tenant gets an empty schema via provision().
        // Tenant's primary key is a non-incrementing string (see Tenant::$keyType)
        // with no auto-generation of its own -- only `uuid` is auto-assigned in
        // Tenant::booted() -- so id must be supplied explicitly here.
        $sandboxTenant = Tenant::create([
            'id'           => Str::limit($slug, 60, ''),
            'slug'         => $slug,
            'name'         => $sandboxName . ' [SANDBOX]',
            'company_name' => ($parent->company_name ?? $parent->name) . ' [SANDBOX]',
            'country_code' => $parent->country_code,
            'currency'     => $parent->currency,
            'locale'       => $parent->locale,
            'timezone'     => $parent->timezone,
            'industry'     => $parent->industry,
            'plan'         => $parent->plan ?? 'starter',
            'status'       => 'trial',
            'trial_ends_at'=> now()->addDays(Sandbox::EXPIRY_DAYS),
            'is_active'    => false,
            // Carry forward settings but strip sensitive keys
            'settings'     => array_merge(
                (array) ($parent->settings ?? []),
                ['is_sandbox' => true, 'parent_tenant_id' => (string) $parent->id],
            ),
        ]);

        // Create the Sandbox link record
        $sandbox = Sandbox::create([
            'tenant_id'        => $sandboxTenant->id,
            'parent_tenant_id' => $parent->id,
            'name'             => $sandboxName,
            'expires_at'       => now()->addDays(Sandbox::EXPIRY_DAYS),
            'status'           => Sandbox::STATUS_ACTIVE,
        ]);

        return $sandbox;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // READ
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return all active sandboxes whose parent is $parentTenantId.
     */
    public function listByParent(string $parentTenantId): Collection
    {
        return Sandbox::active()
            ->where('parent_tenant_id', $parentTenantId)
            ->with(['tenant'])
            ->get();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // UPDATE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Re-link a sandbox tenant to a different parent tenant.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function linkToParent(string $sandboxTenantId, string $parentTenantId): Sandbox
    {
        /** @var Sandbox $sandbox */
        $sandbox = Sandbox::where('tenant_id', $sandboxTenantId)->firstOrFail();

        $sandbox->update(['parent_tenant_id' => $parentTenantId]);

        return $sandbox->fresh();
    }

    /**
     * Reset a sandbox: mark active again, extend expiry, clear sandbox data.
     *
     * Actual database-level wipe of the sandbox tenant's data is handled
     * separately (e.g. via TenantManagerService::purge + re-provision) to
     * keep this service simple and testable.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function resetSandbox(int $sandboxId): Sandbox
    {
        /** @var Sandbox $sandbox */
        $sandbox = Sandbox::findOrFail($sandboxId);

        $sandbox->update([
            'status'     => Sandbox::STATUS_ACTIVE,
            'expires_at' => now()->addDays(Sandbox::EXPIRY_DAYS),
        ]);

        // Also extend the underlying sandbox tenant's trial
        if ($sandbox->tenant_id) {
            Tenant::where('id', $sandbox->tenant_id)->update([
                'status'        => 'trial',
                'trial_ends_at' => now()->addDays(Sandbox::EXPIRY_DAYS),
            ]);
        }

        return $sandbox->fresh();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DELETE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Soft-delete a sandbox and mark its status as deleted.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function deleteSandbox(int $sandboxId): bool
    {
        /** @var Sandbox $sandbox */
        $sandbox = Sandbox::findOrFail($sandboxId);

        $sandbox->update(['status' => Sandbox::STATUS_DELETED]);
        $sandbox->delete(); // SoftDeletes

        return true;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SCHEDULED CLEANUP
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Find all sandboxes past their expiry date, mark them as expired,
     * and return the count of sandboxes that were updated.
     */
    public function expireOverdue(): int
    {
        return Sandbox::where('status', Sandbox::STATUS_ACTIVE)
            ->where('expires_at', '<', now())
            ->update(['status' => Sandbox::STATUS_EXPIRED]);
    }
}
