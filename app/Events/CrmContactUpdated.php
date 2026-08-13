<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\CRM\Models\Contact;

class CrmContactUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Contact $contact,
        public readonly string $action = 'updated'
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("crm.contacts.{$this->contact->tenant_id}");
    }

    public function broadcastAs(): string
    {
        return "contact.{$this->action}";
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->contact->id,
            'name'        => $this->contact->name,
            'email'       => $this->contact->email,
            'phone'       => $this->contact->phone,
            'account_id'  => $this->contact->account_id,
            'status'      => $this->contact->status,
            'updated_at'  => $this->contact->updated_at->toIso8601String(),
        ];
    }
}
