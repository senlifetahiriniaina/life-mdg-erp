<?php

declare(strict_types=1);

namespace Modules\HR\Observers;

use App\Events\HrLeaveRequestUpdated;
use Modules\HR\Models\LeaveRequest;

class LeaveRequestObserver
{
    public function created(LeaveRequest $leaveRequest): void
    {
        HrLeaveRequestUpdated::dispatch($leaveRequest, 'created');
    }

    public function updated(LeaveRequest $leaveRequest): void
    {
        HrLeaveRequestUpdated::dispatch($leaveRequest, 'updated');
    }
}
