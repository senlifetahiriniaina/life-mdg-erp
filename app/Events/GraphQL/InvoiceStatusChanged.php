<?php

declare(strict_types=1);

namespace App\Events\GraphQL;

use Modules\Accounting\Models\Invoice;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class InvoiceStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Invoice $invoice,
        public string $oldStatus,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('invoices');
    }

    public function broadcastAs(): string
    {
        return 'invoiceStatusChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->invoice->id,
            'invoice_id' => $this->invoice->id,
            'number' => $this->invoice->number,
            'oldStatus' => $this->oldStatus,
            'newStatus' => $this->invoice->status,
            'amount' => $this->invoice->amount,
            'updated_at' => $this->invoice->updated_at,
        ];
    }
}
