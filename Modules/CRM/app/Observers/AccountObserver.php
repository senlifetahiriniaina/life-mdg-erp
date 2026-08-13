<?php

declare(strict_types=1);

namespace Modules\CRM\Observers;

use App\Events\CrmAccountUpdated;
use Modules\CRM\Models\Account;

class AccountObserver
{
    public function created(Account $account): void
    {
        CrmAccountUpdated::dispatch($account, 'created');
    }

    public function updated(Account $account): void
    {
        CrmAccountUpdated::dispatch($account, 'updated');
    }
}
