<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\CRM\Models\Account;

class CrmAccountUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Account $account,
        public readonly string $action = 'updated'
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("crm.accounts.{$this->account->tenant_id}");
    }

    public function broadcastAs(): string
    {
        return "account.{$this->action}";
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->account->id,
            'name'       => $this->account->name,
            'industry'   => $this->account->industry,
            'type'       => $this->account->type,
            'status'     => $this->account->status,
            'revenue'    => $this->account->revenue,
            'updated_at' => $this->account->updated_at->toIso8601String(),
        ];
    }
}
