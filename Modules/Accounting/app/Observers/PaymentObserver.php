<?php

declare(strict_types=1);

namespace Modules\Accounting\Observers;

use App\Events\AccountingPaymentRecorded;
use Modules\Accounting\Models\Payment;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        AccountingPaymentRecorded::dispatch($payment);
    }
}
