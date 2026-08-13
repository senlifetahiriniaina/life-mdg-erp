<?php

declare(strict_types=1);

namespace Modules\BI\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\BI\Models\CustomVisualization;

class VisualizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bi.visualization.view-any');
    }

    public function view(User $user, CustomVisualization $visualization): bool
    {
        return $user->can('bi.visualization.view') &&
               $this->belongsToCompany($user, $visualization);
    }

    public function create(User $user): bool
    {
        return $user->can('bi.visualization.create');
    }

    public function update(User $user, CustomVisualization $visualization): bool
    {
        return $user->can('bi.visualization.update') &&
               $this->belongsToCompany($user, $visualization);
    }

    public function delete(User $user, CustomVisualization $visualization): bool
    {
        return $user->can('bi.visualization.delete') &&
               $this->belongsToCompany($user, $visualization);
    }

    public function export(User $user, CustomVisualization $visualization): bool
    {
        return $user->can('bi.visualization.export') &&
               $this->belongsToCompany($user, $visualization);
    }

    public function share(User $user, CustomVisualization $visualization): bool
    {
        return $user->can('bi.visualization.share') &&
               $this->belongsToCompany($user, $visualization);
    }

    public function updatePerformance(User $user, CustomVisualization $visualization): bool
    {
        return $user->can('bi.visualization.update') &&
               $this->belongsToCompany($user, $visualization);
    }

    private function belongsToCompany(User $user, CustomVisualization $visualization): bool
    {
        return $user->company_id === $visualization->company_id;
    }
}
