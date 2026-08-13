<?php

namespace Modules\Achats\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Achats\Models\PurchaseReceipt;

class PurchaseReceiptReadyForInventory
{
    use Dispatchable, SerializesModels;

    public function __construct(public PurchaseReceipt $receipt) {}
}
