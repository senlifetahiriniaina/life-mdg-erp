<?php

declare(strict_types=1);

namespace Modules\BI\Policies;

use App\Models\User;
use Modules\BI\Models\ExternalDataSource;

class ExternalDataPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bi.externaldata.view-any');
    }

    public function view(User $user, ExternalDataSource $source): bool
    {
        return $user->can('bi.externaldata.view') &&
               $this->belongsToCompany($user, $source);
    }

    public function create(User $user): bool
    {
        return $user->can('bi.externaldata.create');
    }

    public function connect(User $user, ExternalDataSource $source): bool
    {
        return $user->can('bi.externaldata.connect') &&
               $this->belongsToCompany($user, $source);
    }

    public function disconnect(User $user, ExternalDataSource $source): bool
    {
        return $user->can('bi.externaldata.disconnect') &&
               $this->belongsToCompany($user, $source);
    }

    public function manageCredentials(User $user, ExternalDataSource $source): bool
    {
        return $user->can('bi.externaldata.manage-credentials') &&
               $this->belongsToCompany($user, $source);
    }

    public function configureSync(User $user, ExternalDataSource $source): bool
    {
        return $user->can('bi.externaldata.configure-sync') &&
               $this->belongsToCompany($user, $source);
    }

    public function transformData(User $user, ExternalDataSource $source): bool
    {
        return $user->can('bi.externaldata.transform-data') &&
               $this->belongsToCompany($user, $source);
    }

    public function viewHistory(User $user, ExternalDataSource $source): bool
    {
        return $user->can('bi.externaldata.view-history') &&
               $this->belongsToCompany($user, $source);
    }

    public function delete(User $user, ExternalDataSource $source): bool
    {
        return $user->can('bi.externaldata.delete') &&
               $this->belongsToCompany($user, $source);
    }

    private function belongsToCompany(User $user, ExternalDataSource $source): bool
    {
        return $user->company_id === $source->company_id;
    }
}
