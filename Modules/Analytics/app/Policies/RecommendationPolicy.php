<?php

namespace Modules\Analytics\Policies;

use App\Models\User;
use Modules\Analytics\Models\Recommendation;

/**
 * Chantier 32.25 (audit 14 couches, Analytics — couche 6) : voir le
 * docblock de `MLModelPolicy` pour le détail du bug de précédence PHP
 * corrigé ici — `act()`/`dismiss()` laissaient tout `admin`, quelle que
 * soit sa société, agir sur/rejeter la recommandation d'une autre société
 * quel que soit son statut réel.
 */
class RecommendationPolicy
{
    private function sameCompany(User $user, Recommendation $recommendation): bool
    {
        return (int) ($user->company_id ?? 0) === (int) ($recommendation->company_id ?? 0);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('analytics.recommendation.view')
            || $user->hasRole('admin');
    }

    public function view(User $user, Recommendation $recommendation): bool
    {
        return $this->sameCompany($user, $recommendation)
            && ($user->hasPermissionTo('analytics.recommendation.view') || $user->hasRole('admin'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('analytics.recommendation.create')
            || $user->hasRole('admin');
    }

    public function act(User $user, Recommendation $recommendation): bool
    {
        return $recommendation->status === 'pending'
            && $this->sameCompany($user, $recommendation)
            && ($user->hasPermissionTo('analytics.recommendation.act') || $user->hasRole('admin'));
    }

    public function dismiss(User $user, Recommendation $recommendation): bool
    {
        return in_array($recommendation->status, ['pending', 'viewed'], true)
            && $this->sameCompany($user, $recommendation)
            && ($user->hasPermissionTo('analytics.recommendation.dismiss') || $user->hasRole('admin'));
    }
}
