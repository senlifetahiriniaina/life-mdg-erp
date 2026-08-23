<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Achats\Models\Supplier as AchatsSupplier;

/**
 * Chantier 32 / 32.22 (two independent, concurrent passes at the same gap —
 * reconciled by merge, not a rewrite): this module had zero company/tenant
 * scoping of any kind — confirmed empirically with 2 real companies that any
 * authenticated user with an Inventory role could read/edit/delete any other
 * company's data. `Product` already carries a `tenant_id` column and the
 * `BelongsToTenant` trait (stancl/tenancy, keyed on the real
 * `tenancy()->tenant` context), but that mechanism is a no-op outside a real
 * tenant-domain request (confirmed: `tenant_id` was NULL on every product
 * created via the real API) — it does not close this gap in practice,
 * matching the same "phantom tenant_id" family of bugs documented
 * repeatedly elsewhere in this app.
 *
 * Matches `Modules\Achats\Http\Controllers\Api\Concerns\ScopesToCompany`'s
 * exact shape: `$user->company_id` (never the phantom `users.tenant_id`), a
 * 404 (not 403) on cross-company access — never confirming another
 * company's record even exists — and a null==null "untagged bucket"
 * comparison so pre-chantier data / a not-yet-provisioned user isn't
 * auto-denied.
 *
 * Used directly by controllers whose model has no registered Policy
 * (Category, Lot, Unit, Product, Supplier, and the Inventory-Workflow
 * sibling models) — matching Achats' own proportionality precedent: it did
 * not create a dedicated Policy class per model for its tenant-isolation
 * fix either, only applied this trait where no policy/authorize() call
 * already existed. Models that DO have a registered Policy (Warehouse,
 * StockMovement, Stock, SourcingBenchmark, CostingSheet, ProductionOrder)
 * get `authorize()` (permission, 403) + `assertSameCompany()` (per-record
 * ownership, 404) as two separate calls in the controller, matching
 * Achats' real precedent (PurchaseOrderController) rather than folding the
 * company check into the Policy itself.
 */
trait ScopesToCompany
{
    protected function companyId(Request $request): ?int
    {
        return $request->user()?->company_id;
    }

    /** Scope a query builder to the caller's own company bucket. */
    protected function scopeToCompany(Builder $query, Request $request): Builder
    {
        return $query->where('company_id', $this->companyId($request));
    }

    /**
     * Aborts with 404 (not 403) if the given record belongs to a different
     * company than the caller. A record with company_id === null (legacy
     * data, or created before this chantier) is treated as the same
     * "untagged" bucket as a caller with no real company_id — never
     * auto-denied, matching this app's established convention.
     */
    protected function assertSameCompany(Request $request, Model $model): void
    {
        abort_unless($model->company_id === $this->companyId($request), 404);
    }

    /**
     * Same check for a child/line-item model that has no company_id column
     * of its own (e.g. CostingSheetLine) — resolves the boundary through
     * the named parent relation instead, mirroring
     * Modules\Projects\...\ScopesToProjectCompany's null-safe-walk shape:
     * a no-op whenever either side lacks a real company_id, a real 404
     * only when both sides carry one and they genuinely differ.
     */
    protected function assertSameCompanyViaParent(Request $request, Model $child, string $parentRelation): void
    {
        $userCompanyId = $this->companyId($request);
        $parentCompanyId = $child->{$parentRelation}?->company_id;

        if ($userCompanyId !== null && $parentCompanyId !== null
            && (int) $parentCompanyId !== (int) $userCompanyId) {
            abort(404);
        }
    }

    /**
     * Chantier 32 (cross-module FK consistency): `inventory_production_orders.
     * subcontractor_supplier_id` and `inventory_costing_sheet_lines.supplier_id`
     * both reference `achats_suppliers` (a real constrained FK, already scoped
     * to company_id by an earlier Achats chantier). A caller could otherwise
     * submit a real supplier id belonging to a different company than their
     * own, silently linking their cost/production data to a competitor's
     * supplier record — rejected with a 422 rather than accepted. A supplier
     * id is only validated when one is actually submitted (both fields are
     * nullable); a supplier with no real company_id (legacy data) is treated
     * as the same "untagged" bucket, matching this app's established
     * null==null convention.
     *
     * @throws ValidationException
     */
    protected function assertSupplierBelongsToCompany(Request $request, ?int $supplierId, string $field = 'supplier_id'): void
    {
        if ($supplierId === null) {
            return;
        }

        $supplier = AchatsSupplier::find($supplierId);

        if ($supplier === null) {
            return; // let the request's own exists: rule handle a genuinely unknown id
        }

        $userCompanyId = $this->companyId($request);
        $supplierCompanyId = $supplier->company_id;

        if ((int) ($userCompanyId ?? 0) !== (int) ($supplierCompanyId ?? 0)) {
            throw ValidationException::withMessages([
                $field => ["Le fournisseur sélectionné n'appartient pas à votre société."],
            ]);
        }
    }
}
