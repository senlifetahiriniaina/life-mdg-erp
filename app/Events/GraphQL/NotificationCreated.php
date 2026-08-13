<?php

declare(strict_types=1);

namespace App\Events\GraphQL;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $userId,
        public string $title,
        public string $message,
        public string $type,
        public ?int $relatedId = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('notifications.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'notificationReceived';
    }

    public function broadcastWith(): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'relatedId' => $this->relatedId,
            'createdAt' => now(),
        ];
    }
}
