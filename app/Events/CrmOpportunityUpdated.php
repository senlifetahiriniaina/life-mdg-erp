<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\CRM\Models\Opportunity;

class CrmOpportunityUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Opportunity $opportunity,
        public readonly string $action = 'updated'
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("crm.opportunities.{$this->opportunity->tenant_id}");
    }

    public function broadcastAs(): string
    {
        return "opportunity.{$this->action}";
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->opportunity->id,
            'name'        => $this->opportunity->name,
            'amount'      => $this->opportunity->amount,
            'stage'       => $this->opportunity->stage,
            'probability' => $this->opportunity->probability,
            'owner_id'    => $this->opportunity->owner_id,
            'account_id'  => $this->opportunity->account_id,
            'updated_at'  => $this->opportunity->updated_at->toIso8601String(),
        ];
    }
}
