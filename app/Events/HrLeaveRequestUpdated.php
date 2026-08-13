<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\HR\Models\LeaveRequest;

class HrLeaveRequestUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LeaveRequest $leaveRequest,
        public readonly string $action = 'updated'
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("hr.leaves.{$this->leaveRequest->tenant_id}");
    }

    public function broadcastAs(): string
    {
        return "leave.{$this->action}";
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->leaveRequest->id,
            'employee_id'  => $this->leaveRequest->employee_id,
            'type'         => $this->leaveRequest->type,
            'status'       => $this->leaveRequest->status,
            'start_date'   => $this->leaveRequest->start_date->toIso8601String(),
            'end_date'     => $this->leaveRequest->end_date->toIso8601String(),
            'days'         => $this->leaveRequest->days,
            'updated_at'   => $this->leaveRequest->updated_at->toIso8601String(),
        ];
    }
}
