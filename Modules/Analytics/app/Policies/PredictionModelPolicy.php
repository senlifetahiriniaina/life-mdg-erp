<?php

namespace Modules\Analytics\Policies;

use App\Models\User;
use Modules\Analytics\Models\PredictionModel;

class PredictionModelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('analytics.prediction.view')
            || $user->hasRole('admin');
    }

    public function view(User $user, PredictionModel $model): bool
    {
        return ($user->company_id === $model->company_id && $user->hasPermissionTo('analytics.prediction.view'))
            || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('analytics.prediction.create')
            || $user->hasRole('admin');
    }

    public function update(User $user, PredictionModel $model): bool
    {
        return $model->status === 'draft'
            && ($user->company_id === $model->company_id && $user->hasPermissionTo('analytics.prediction.update'))
            || $user->hasRole('admin');
    }

    public function train(User $user, PredictionModel $model): bool
    {
        return $user->company_id === $model->company_id
            && ($user->hasPermissionTo('analytics.prediction.train') || $user->hasRole('admin'));
    }

    public function delete(User $user, PredictionModel $model): bool
    {
        return $user->company_id === $model->company_id
            && ($user->hasPermissionTo('analytics.prediction.delete') || $user->hasRole('admin'));
    }
}
