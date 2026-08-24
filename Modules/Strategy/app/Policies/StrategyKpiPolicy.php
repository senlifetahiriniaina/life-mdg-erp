<?php

declare(strict_types=1);

namespace Modules\Strategy\Policies;

use App\Models\User;
use Modules\Strategy\Models\StrategyKpi;

/**
 * Chantier 32.27 (audit 14 couches — layer 6, sécurité approfondie):
 * view()/update()/delete() were an unconditional `true`/role-only check with
 * no per-record tenant ownership check — confirmed empirically that any
 * strategy-analyst/admin of Company A could update/delete Company B's real
 * KPI definition by id via KpiController::update()/destroy(), and
 * values()/refresh() (both real HTTP GET/POST endpoints) had zero authorize()
 * call and zero tenant scoping at all. Fixed with a real same-company check
 * here, plus the missing authorize()/tenant-ownership check added on the
 * controller side (see KpiController).
 */
class StrategyKpiPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StrategyKpi $strategyKpi): bool
    {
        return $this->sameCompany($user, $strategyKpi);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['strategy-analyst', 'admin', 'super-admin'])
            || $user->hasPermissionTo('strategy.kpi.create');
    }

    public function update(User $user, StrategyKpi $strategyKpi): bool
    {
        return $this->sameCompany($user, $strategyKpi)
            && ($user->hasAnyRole(['strategy-analyst', 'admin', 'super-admin'])
                || $user->hasPermissionTo('strategy.kpi.update'));
    }

    public function delete(User $user, StrategyKpi $strategyKpi): bool
    {
        return $this->sameCompany($user, $strategyKpi)
            && ($user->hasAnyRole(['admin', 'super-admin'])
                || $user->hasPermissionTo('strategy.kpi.delete'));
    }

    private function sameCompany(User $user, StrategyKpi $strategyKpi): bool
    {
        return (string) ($user->company_id ?? 0) === (string) ($strategyKpi->tenant_id ?? '');
    }

    public function restore(User $user, StrategyKpi $strategyKpi): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function forceDelete(User $user, StrategyKpi $strategyKpi): bool
    {
        return $user->hasRole('super-admin');
    }
}
