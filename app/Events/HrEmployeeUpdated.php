<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\HR\Models\Employee;

class HrEmployeeUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Employee $employee,
        public readonly string $action = 'updated'
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("hr.employees.{$this->employee->tenant_id}");
    }

    public function broadcastAs(): string
    {
        return "employee.{$this->action}";
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->employee->id,
            'name'        => $this->employee->name,
            'email'       => $this->employee->email,
            'job_title'   => $this->employee->job_title,
            'department'  => $this->employee->department,
            'status'      => $this->employee->status,
            'updated_at'  => $this->employee->updated_at->toIso8601String(),
        ];
    }
}
