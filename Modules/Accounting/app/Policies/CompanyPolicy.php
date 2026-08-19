<?php

namespace Modules\Accounting\Policies;

use App\Models\User;
use Modules\Accounting\Models\Company;

/**
 * Chantier 19 re-verification: `Modules\Accounting\Models\Company` (the multi-entity
 * consolidation-group model behind ConsolidationController — Consolidation/{Create,
 * Detail,Index}.vue) had ZERO policy of any kind despite ConsolidationController
 * calling `$this->authorize()` against `create`/`update`/`generateReport`/
 * `recordTransaction`/`eliminateIntercompany` on every mutating action — confirmed
 * empirically (Gate::getPolicyFor() returned null, and a real accountant/
 * finance-manager/manager/admin request — the exact roles this route group's own
 * `role:` middleware is scoped to — got an unconditional 403 on POST consolidations).
 * Laravel denies by default when a model has no policy and no action-name Gate
 * definition, so every one of those endpoints was 100% unreachable for anyone
 * short of super-admin's Gate::before bypass. Reuses the already-seeded
 * `accounting.consolidation.*` permission prefix (shared with the sibling
 * ConsolidationHierarchy concept — both are part of the same "multi-company
 * consolidation" feature), extending it with 3 non-standard verbs seeded via
 * ACCOUNTING_EXTRA_PERMISSIONS.
 */
class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.view-any');
    }

    public function view(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.create');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.update');
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.delete');
    }

    public function generateReport(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.generate-report');
    }

    public function recordTransaction(User $user): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.record-transaction');
    }

    public function eliminateIntercompany(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.eliminate-intercompany');
    }

    public function restore(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.restore');
    }

    public function forceDelete(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('accounting.consolidation.force_delete');
    }
}
