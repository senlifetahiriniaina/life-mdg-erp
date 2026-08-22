<?php

namespace Modules\Analytics\Policies;

use App\Models\User;
use Modules\Analytics\Models\PredictionModel;

/**
 * Chantier 32.25 (audit 14 couches, Analytics — couche 6) : voir le
 * docblock de `MLModelPolicy` pour le détail du bug de précédence PHP
 * (`&&`/`||`) corrigé ici de la même façon — `update()` laissait
 * auparavant tout `admin`, quelle que soit sa société, contourner à la
 * fois le cloisonnement société et le statut `draft` requis.
 */
class PredictionModelPolicy
{
    private function sameCompany(User $user, PredictionModel $model): bool
    {
        return (int) ($user->company_id ?? 0) === (int) ($model->company_id ?? 0);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('analytics.prediction.view')
            || $user->hasRole('admin');
    }

    public function view(User $user, PredictionModel $model): bool
    {
        return $this->sameCompany($user, $model)
            && ($user->hasPermissionTo('analytics.prediction.view') || $user->hasRole('admin'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('analytics.prediction.create')
            || $user->hasRole('admin');
    }

    public function update(User $user, PredictionModel $model): bool
    {
        return $model->status === 'draft'
            && $this->sameCompany($user, $model)
            && ($user->hasPermissionTo('analytics.prediction.update') || $user->hasRole('admin'));
    }

    public function train(User $user, PredictionModel $model): bool
    {
        return $this->sameCompany($user, $model)
            && ($user->hasPermissionTo('analytics.prediction.train') || $user->hasRole('admin'));
    }

    public function delete(User $user, PredictionModel $model): bool
    {
        return $this->sameCompany($user, $model)
            && ($user->hasPermissionTo('analytics.prediction.delete') || $user->hasRole('admin'));
    }
}
