<?php

namespace Modules\CRM\Policies;

use App\Models\User;
use Modules\CRM\Models\RevenueAnomaly;
use Modules\CRM\Models\RevenueInsight;
use Modules\CRM\Models\RevenueTrend;

/**
 * Chantier 10 fix: RevenueIntelligenceController (insights/trends/anomalies) had zero
 * authorize() calls and zero tenant scoping — any authenticated user of any company could
 * read and resolve every other company's revenue insights/trends/anomalies. This single
 * policy backs RevenueInsight/RevenueTrend/RevenueAnomaly (all 3 share the same "revenue
 * intelligence" resource-permission and the same freshly-added company_id column), mirroring
 * CustomerServiceAIPolicy's precedent of one policy fanning out over several related models
 * that don't each need their own class.
 */
class RevenueInsightPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'crm.revenue-intelligence.view');
    }

    public function view(User $user, RevenueInsight|RevenueTrend|RevenueAnomaly $model): bool
    {
        return $this->can($user, 'crm.revenue-intelligence.view')
            && $this->sameCompany($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'crm.revenue-intelligence.create');
    }

    public function resolve(User $user, RevenueInsight|RevenueTrend|RevenueAnomaly $model): bool
    {
        return $this->can($user, 'crm.revenue-intelligence.resolve')
            && $this->sameCompany($user, $model);
    }

    /**
     * hasPermissionTo() throws PermissionDoesNotExist (not a false-y denial) when the
     * permission row itself hasn't been seeded yet — these crm.revenue-intelligence.* strings
     * are new in this chantier and are not yet in RolesAndPermissionsSeeder's
     * CRM_EXTRA_PERMISSIONS block (Phase B's job, per this chantier's scope boundary). Fail
     * closed (deny) rather than let an unseeded permission 500 every request, matching this
     * session's established "fails closed, denies everyone" acceptable-severity precedent
     * (see the Security company_id-cast bug elsewhere in this codebase) rather than the worse
     * "throws for everyone" behavior Spatie defaults to.
     */
    private function can(User $user, string $permission): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        try {
            return $user->hasPermissionTo($permission);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            return false;
        }
    }

    /**
     * company_id is nullable (additive patch on pre-existing rows) — a null value on either
     * side is treated as "no tenant boundary recorded", matching this session's established
     * ?? 0 sentinel convention rather than leaving NULL === NULL to accidentally pass/fail.
     */
    private function sameCompany(User $user, RevenueInsight|RevenueTrend|RevenueAnomaly $model): bool
    {
        return $user->hasRole('admin')
            || ((int) ($user->company_id ?? 0)) === ((int) ($model->company_id ?? 0));
    }
}
