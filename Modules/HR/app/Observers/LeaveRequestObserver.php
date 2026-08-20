<?php

declare(strict_types=1);

namespace Modules\HR\Observers;

use App\Events\HrLeaveRequestUpdated;
use Modules\Core\Services\ParticipantNotificationService;
use Modules\HR\Models\LeaveRequest;

class LeaveRequestObserver
{
    public function __construct(private readonly ParticipantNotificationService $notifier)
    {
    }

    public function created(LeaveRequest $leaveRequest): void
    {
        HrLeaveRequestUpdated::dispatch($leaveRequest, 'created');

        // Owner of this action is the requesting employee — notifyProcess()
        // resolves and notifies their direct manager on its own via the
        // real Employee.manager_id chain, no explicit participant needed.
        $this->notifier->notifyProcess(
            [],
            $leaveRequest->employee?->user,
            'Nouvelle demande de congé',
            sprintf(
                'Une demande de congé (%s au %s) attend votre validation.',
                optional($leaveRequest->start_date)->format('d/m/Y'),
                optional($leaveRequest->end_date)->format('d/m/Y'),
            ),
            ['type' => 'warning', 'action_url' => '/hr/leaves'],
        );
    }

    public function updated(LeaveRequest $leaveRequest): void
    {
        HrLeaveRequestUpdated::dispatch($leaveRequest, 'updated');

        if (! $leaveRequest->wasChanged('status') || ! in_array($leaveRequest->status, ['approved', 'rejected'], true)) {
            return;
        }

        $this->notifier->notifyProcess(
            array_filter([$leaveRequest->employee?->user]),
            $leaveRequest->approver?->user,
            $leaveRequest->status === 'approved' ? 'Congé approuvé' : 'Congé rejeté',
            $leaveRequest->status === 'approved'
                ? 'Votre demande de congé a été approuvée.'
                : sprintf('Votre demande de congé a été rejetée. %s', $leaveRequest->approval_notes ?? ''),
            [
                'type' => $leaveRequest->status === 'approved' ? 'success' : 'error',
                'action_url' => '/hr/leaves',
            ],
        );
    }
}
