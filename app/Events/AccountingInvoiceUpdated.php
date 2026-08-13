<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Accounting\Models\Invoice;

class AccountingInvoiceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $action = 'updated'
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("accounting.invoices.{$this->invoice->tenant_id}");
    }

    public function broadcastAs(): string
    {
        return "invoice.{$this->action}";
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'customer_id' => $this->invoice->customer_id,
            'status'      => $this->invoice->status,
            'total'       => $this->invoice->total,
            'paid_amount' => $this->invoice->paid_amount ?? 0,
            'due_date'    => $this->invoice->due_date?->toIso8601String(),
            'updated_at'  => $this->invoice->updated_at->toIso8601String(),
        ];
    }
}
