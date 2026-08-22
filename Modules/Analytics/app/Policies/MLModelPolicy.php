<?php

namespace Modules\Analytics\Policies;

use App\Models\User;
use Modules\Analytics\Models\MLModel;

/**
 * Chantier 32.25 (audit 14 couches, Analytics — couche 6, sécurité
 * approfondie / IDOR) : confirmé empiriquement (`view`/`update`/`deploy`
 * tous `true`) qu'un `admin` de la société A pouvait consulter, mettre à
 * jour, et déployer/rollback/supprimer le modèle ML d'une société B —
 * `'admin'` est un rôle Spatie global dans cette app (jamais scopé par
 * société, motif déjà documenté ~10 fois dans ce fichier), et
 * `$condition && (...) || $user->hasRole('admin')` place implicitement le
 * bypass admin en dehors de toute parenthèse à cause de la précédence PHP
 * (&& lie plus fort que ||) — un `admin` contournait donc à la fois le
 * cloisonnement société ET, pour `deploy()`/`rollback()`/`delete()`, la
 * contrainte de statut métier (peut déployer un modèle qui n'est pas en
 * `staging`, etc.). Corrigé selon le même précédent déjà établi au
 * Chantier 31 pour `Modules\Validation\Policies\ApprovalRequestPolicy` :
 * le bypass `admin` reste réel, mais toujours scopé à la même société —
 * jamais un contournement global inter-sociétés.
 */
class MLModelPolicy
{
    private function sameCompany(User $user, MLModel $model): bool
    {
        return (int) ($user->company_id ?? 0) === (int) ($model->company_id ?? 0);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('analytics.ml_model.view')
            || $user->hasRole('admin');
    }

    public function view(User $user, MLModel $model): bool
    {
        return $this->sameCompany($user, $model)
            && ($user->hasPermissionTo('analytics.ml_model.view') || $user->hasRole('admin'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('analytics.ml_model.create')
            || $user->hasRole('admin');
    }

    public function update(User $user, MLModel $model): bool
    {
        return $this->sameCompany($user, $model)
            && ($user->hasPermissionTo('analytics.ml_model.update') || $user->hasRole('admin'));
    }

    public function deploy(User $user, MLModel $model): bool
    {
        return $model->status === 'staging'
            && $this->sameCompany($user, $model)
            && ($user->hasPermissionTo('analytics.ml_model.deploy') || $user->hasRole('admin'));
    }

    public function rollback(User $user, MLModel $model): bool
    {
        return $model->status === 'production'
            && $this->sameCompany($user, $model)
            && ($user->hasPermissionTo('analytics.ml_model.rollback') || $user->hasRole('admin'));
    }

    public function delete(User $user, MLModel $model): bool
    {
        return $model->status !== 'production'
            && $this->sameCompany($user, $model)
            && ($user->hasPermissionTo('analytics.ml_model.delete') || $user->hasRole('admin'));
    }
}
