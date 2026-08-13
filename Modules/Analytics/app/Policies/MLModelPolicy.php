<?php

namespace Modules\Analytics\Policies;

use App\Models\User;
use Modules\Analytics\Models\MLModel;

class MLModelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('analytics.ml_model.view')
            || $user->hasRole('admin');
    }

    public function view(User $user, MLModel $model): bool
    {
        return ($user->company_id === $model->company_id && $user->hasPermissionTo('analytics.ml_model.view'))
            || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('analytics.ml_model.create')
            || $user->hasRole('admin');
    }

    public function update(User $user, MLModel $model): bool
    {
        return ($user->company_id === $model->company_id && $user->hasPermissionTo('analytics.ml_model.update'))
            || $user->hasRole('admin');
    }

    public function deploy(User $user, MLModel $model): bool
    {
        return $model->status === 'staging'
            && ($user->company_id === $model->company_id && $user->hasPermissionTo('analytics.ml_model.deploy'))
            || $user->hasRole('admin');
    }

    public function rollback(User $user, MLModel $model): bool
    {
        return $model->status === 'production'
            && ($user->company_id === $model->company_id && $user->hasPermissionTo('analytics.ml_model.rollback'))
            || $user->hasRole('admin');
    }

    public function delete(User $user, MLModel $model): bool
    {
        return $model->status !== 'production'
            && ($user->company_id === $model->company_id && $user->hasPermissionTo('analytics.ml_model.delete'))
            || $user->hasRole('admin');
    }
}
