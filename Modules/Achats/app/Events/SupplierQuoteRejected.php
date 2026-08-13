<?php

namespace Modules\Achats\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Achats\Models\SupplierQuote;

class SupplierQuoteRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SupplierQuote $quote,
        public string $reason
    ) {}
}
