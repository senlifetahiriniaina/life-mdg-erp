<?php

namespace Modules\Achats\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Achats\Models\PurchaseReceipt;
use Modules\Achats\Models\PurchaseReceiptLine;

class QualityIssueRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PurchaseReceipt $receipt,
        public PurchaseReceiptLine $line,
        public string $issueType,
        public string $description
    ) {}
}
