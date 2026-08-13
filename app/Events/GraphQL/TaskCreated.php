<?php

declare(strict_types=1);

namespace App\Events\GraphQL;

use Modules\Projects\Models\Task;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TaskCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Task $task) {}

    public function broadcastOn(): Channel
    {
        return new Channel('projects');
    }

    public function broadcastAs(): string
    {
        return 'taskCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->task->id,
            'project_id' => $this->task->project_id,
            'title' => $this->task->title,
            'status' => $this->task->status,
            'created_at' => $this->task->created_at,
        ];
    }
}
