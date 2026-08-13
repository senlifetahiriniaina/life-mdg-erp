<?php

namespace Modules\Achats\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseReceipt;

class PurchaseOrderReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PurchaseOrder $purchaseOrder,
        public PurchaseReceipt $receipt
    ) {}
}
