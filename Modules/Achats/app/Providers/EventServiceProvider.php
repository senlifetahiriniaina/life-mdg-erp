<?php

namespace Modules\Achats\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Achats\Events\PurchaseOrderApproved;
use Modules\Achats\Events\PurchaseOrderCancelled;
use Modules\Achats\Events\PurchaseOrderInvoiced;
use Modules\Achats\Events\PurchaseOrderReadyForAccounting;
use Modules\Achats\Events\PurchaseOrderReceived;
use Modules\Achats\Events\PurchaseOrderSubmittedForApproval;
use Modules\Achats\Events\PurchaseReceiptCompleted;
use Modules\Achats\Events\PurchaseReceiptReadyForInventory;
use Modules\Achats\Events\RFQIssued;
use Modules\Achats\Events\SupplierQuoteAccepted;
use Modules\Achats\Events\SupplierQuoteReceived;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Purchase Order events
        PurchaseOrderSubmittedForApproval::class => [
            // Event listeners
        ],
        PurchaseOrderApproved::class => [
            // Event listeners
        ],
        PurchaseOrderReceived::class => [
            // Event listeners
        ],
        PurchaseOrderInvoiced::class => [
            // Event listeners
        ],
        PurchaseOrderCancelled::class => [
            // Event listeners
        ],

        // RFQ events
        RFQIssued::class => [
            // Event listeners
        ],
        SupplierQuoteReceived::class => [
            // Event listeners
        ],
        SupplierQuoteAccepted::class => [
            // Event listeners
        ],

        // Receipt events
        PurchaseReceiptCompleted::class => [
            // Event listeners
        ],

        // Integration events
        PurchaseOrderReadyForAccounting::class => [
            // Event listeners
        ],
        PurchaseReceiptReadyForInventory::class => [
            // Event listeners
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
