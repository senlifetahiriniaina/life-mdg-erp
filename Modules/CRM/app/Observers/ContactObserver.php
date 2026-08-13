<?php

declare(strict_types=1);

namespace Modules\CRM\Observers;

use App\Events\CrmContactUpdated;
use Modules\CRM\Models\Contact;

class ContactObserver
{
    public function created(Contact $contact): void
    {
        CrmContactUpdated::dispatch($contact, 'created');
    }

    public function updated(Contact $contact): void
    {
        CrmContactUpdated::dispatch($contact, 'updated');
    }
}
