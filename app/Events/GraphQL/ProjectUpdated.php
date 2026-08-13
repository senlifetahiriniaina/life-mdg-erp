<?php

declare(strict_types=1);

namespace App\Events\GraphQL;

use Modules\Projects\Models\Project;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ProjectUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Project $project) {}

    public function broadcastOn(): Channel
    {
        return new Channel('projects');
    }

    public function broadcastAs(): string
    {
        return 'projectUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->project->id,
            'name' => $this->project->name,
            'status' => $this->project->status,
            'progress' => $this->project->progress,
            'updated_at' => $this->project->updated_at,
        ];
    }
}
