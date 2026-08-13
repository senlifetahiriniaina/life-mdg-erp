<?php

declare(strict_types=1);

namespace Modules\AuditLog\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('auditlog.auditlog.view-any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('auditlog.auditlog.view');
    }

    public function create(User $user): bool
    {
        return $user->can('auditlog.auditlog.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('auditlog.auditlog.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('auditlog.auditlog.delete');
    }

    public function approve(User $user, Model $model): bool
    {
        return $user->can('auditlog.auditlog.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('auditlog.auditlog.export');
    }

    public function archive(User $user, Model $model): bool
    {
        return $user->can('auditlog.auditlog.archive');
    }
}