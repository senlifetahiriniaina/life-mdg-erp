<?php

declare(strict_types=1);

namespace Modules\BI\Policies;

use App\Models\User;
use Modules\BI\Models\ForecastModel;

class ForecastingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bi.forecasting.view-any');
    }

    public function view(User $user, ForecastModel $model): bool
    {
        return $user->can('bi.forecasting.view') &&
               $this->belongsToCompany($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can('bi.forecasting.create');
    }

    public function train(User $user, ForecastModel $model): bool
    {
        return $user->can('bi.forecasting.train') &&
               $this->belongsToCompany($user, $model);
    }

    public function deploy(User $user, ForecastModel $model): bool
    {
        return $user->can('bi.forecasting.deploy') &&
               $this->belongsToCompany($user, $model);
    }

    public function delete(User $user, ForecastModel $model): bool
    {
        return $user->can('bi.forecasting.delete') &&
               $this->belongsToCompany($user, $model);
    }

    public function archive(User $user, ForecastModel $model): bool
    {
        return $user->can('bi.forecasting.archive') &&
               $this->belongsToCompany($user, $model);
    }

    public function viewPredictions(User $user, ForecastModel $model): bool
    {
        return $user->can('bi.forecasting.view-predictions') &&
               $this->belongsToCompany($user, $model);
    }

    public function manageScenarios(User $user, ForecastModel $model): bool
    {
        return $user->can('bi.forecasting.manage-scenarios') &&
               $this->belongsToCompany($user, $model);
    }

    public function viewAccuracy(User $user, ForecastModel $model): bool
    {
        return $user->can('bi.forecasting.view-accuracy') &&
               $this->belongsToCompany($user, $model);
    }

    private function belongsToCompany(User $user, ForecastModel $model): bool
    {
        return $user->company_id === $model->company_id;
    }
}
