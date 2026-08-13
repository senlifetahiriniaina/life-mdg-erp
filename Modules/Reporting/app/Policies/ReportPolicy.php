<?php

declare(strict_types=1);

namespace Modules\Reporting\Policies;

use App\Models\User;
use Modules\Reporting\Models\ReportDefinition;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reporting.report.view-any');
    }

    public function view(User $user, ReportDefinition $report): bool
    {
        return $user->can('reporting.report.view');
    }

    public function create(User $user): bool
    {
        return $user->can('reporting.report.create');
    }

    public function update(User $user, ReportDefinition $report): bool
    {
        return $user->can('reporting.report.update');
    }

    public function delete(User $user, ReportDefinition $report): bool
    {
        return $user->can('reporting.report.delete');
    }
}
