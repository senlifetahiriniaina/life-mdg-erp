<?php

declare(strict_types=1);

namespace Modules\Accounting\Observers;

use App\Events\AccountingInvoiceUpdated;
use Modules\Accounting\Models\Invoice;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        AccountingInvoiceUpdated::dispatch($invoice, 'created');
    }

    public function updated(Invoice $invoice): void
    {
        AccountingInvoiceUpdated::dispatch($invoice, 'updated');
    }
}
