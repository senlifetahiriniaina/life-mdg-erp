<?php

declare(strict_types=1);

namespace Modules\Setup\Policies;

use App\Models\User;
use Modules\Setup\Models\ImportJob;

class ImportSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('setup.import.view-any');
    }

    public function view(User $user, ImportJob $importJob): bool
    {
        return $user->can('setup.import.view');
    }

    public function create(User $user): bool
    {
        return $user->can('setup.import.create');
    }

    public function update(User $user, ImportJob $importJob): bool
    {
        return $user->can('setup.import.update');
    }

    public function delete(User $user, ImportJob $importJob): bool
    {
        return $user->can('setup.import.delete');
    }
}
