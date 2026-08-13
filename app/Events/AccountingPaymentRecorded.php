<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Accounting\Models\Payment;

class AccountingPaymentRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Payment $payment) {}

    public function broadcastOn(): Channel
    {
        return new Channel("accounting.payments.{$this->payment->tenant_id}");
    }

    public function broadcastAs(): string
    {
        return 'payment.recorded';
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->payment->id,
            'invoice_id'   => $this->payment->invoice_id,
            'amount'       => $this->payment->amount,
            'method'       => $this->payment->method,
            'reference'    => $this->payment->reference,
            'payment_date' => $this->payment->payment_date->toIso8601String(),
            'created_at'   => $this->payment->created_at->toIso8601String(),
        ];
    }
}
