<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\WhatsApp\Models\Message;

class WaMessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Message $message) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("whatsapp.conversation.{$this->message->conversation_id}");
    }

    public function broadcastAs(): string
    {
        return 'message.received';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id'              => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'body'            => $this->message->content,
            'content'         => $this->message->content,
            'direction'       => $this->message->direction,
            'type'            => $this->message->type,
            'created_at'      => $this->message->created_at?->toISOString(),
        ];
    }
}
