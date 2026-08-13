<?php

namespace Modules\Achats\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Achats\Models\RFQ;
use Modules\Achats\Models\SupplierQuote;

class SupplierQuoteReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public RFQ $rfq,
        public SupplierQuote $quote
    ) {}
}
