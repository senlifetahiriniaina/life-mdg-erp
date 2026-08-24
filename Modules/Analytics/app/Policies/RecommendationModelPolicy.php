<?php

namespace Modules\Analytics\Policies;

use App\Models\User;
use Modules\Analytics\Models\RecommendationModel;

/**
 * Chantier 32.25 (audit 14 couches, Analytics — couche 6) : voir le
 * docblock de `MLModelPolicy` pour le détail du bug de précédence PHP
 * corrigé ici — `view()`/`update()` laissaient tout `admin`, quelle que
 * soit sa société, contourner le cloisonnement société.
 */
class RecommendationModelPolicy
{
    private function sameCompany(User $user, RecommendationModel $model): bool
    {
        return (int) ($user->company_id ?? 0) === (int) ($model->company_id ?? 0);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('analytics.recommendation.view')
            || $user->hasRole('admin');
    }

    public function view(User $user, RecommendationModel $model): bool
    {
        return $this->sameCompany($user, $model)
            && ($user->hasPermissionTo('analytics.recommendation.view') || $user->hasRole('admin'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('analytics.recommendation.create')
            || $user->hasRole('admin');
    }

    public function update(User $user, RecommendationModel $model): bool
    {
        return $this->sameCompany($user, $model)
            && ($user->hasPermissionTo('analytics.recommendation.update') || $user->hasRole('admin'));
    }

    public function train(User $user, RecommendationModel $model): bool
    {
        return $this->sameCompany($user, $model)
            && ($user->hasPermissionTo('analytics.recommendation.train') || $user->hasRole('admin'));
    }

    public function delete(User $user, RecommendationModel $model): bool
    {
        return $this->sameCompany($user, $model)
            && ($user->hasPermissionTo('analytics.recommendation.delete') || $user->hasRole('admin'));
    }
}
