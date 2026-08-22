<?php

namespace Modules\Analytics\Policies;

use App\Models\User;
use Modules\Analytics\Models\ABTestRun;

/**
 * Chantier 32.25 (audit 14 couches, Analytics — couche 6) : voir le
 * docblock de `MLModelPolicy` pour le détail du bug de précédence PHP
 * corrigé ici — `start()`/`complete()`/`deploy()` laissaient tout `admin`,
 * quelle que soit sa société, contourner à la fois le cloisonnement
 * société et la contrainte de statut du test A/B.
 */
class ABTestRunPolicy
{
    private function sameCompany(User $user, ABTestRun $test): bool
    {
        return (int) ($user->company_id ?? 0) === (int) ($test->company_id ?? 0);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('analytics.ab_test.view')
            || $user->hasRole('admin');
    }

    public function view(User $user, ABTestRun $test): bool
    {
        return $this->sameCompany($user, $test)
            && ($user->hasPermissionTo('analytics.ab_test.view') || $user->hasRole('admin'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('analytics.ab_test.create')
            || $user->hasRole('admin');
    }

    public function start(User $user, ABTestRun $test): bool
    {
        return $test->status === 'planned'
            && $this->sameCompany($user, $test)
            && ($user->hasPermissionTo('analytics.ab_test.start') || $user->hasRole('admin'));
    }

    public function complete(User $user, ABTestRun $test): bool
    {
        return $test->status === 'running'
            && $this->sameCompany($user, $test)
            && ($user->hasPermissionTo('analytics.ab_test.complete') || $user->hasRole('admin'));
    }

    public function deploy(User $user, ABTestRun $test): bool
    {
        return $test->status === 'completed' && $test->winner
            && $this->sameCompany($user, $test)
            && ($user->hasPermissionTo('analytics.ab_test.deploy') || $user->hasRole('admin'));
    }

    public function delete(User $user, ABTestRun $test): bool
    {
        return $test->status === 'planned'
            && $this->sameCompany($user, $test)
            && ($user->hasPermissionTo('analytics.ab_test.delete') || $user->hasRole('admin'));
    }
}
