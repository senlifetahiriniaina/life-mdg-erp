<?php

namespace Modules\Validation\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Validation\Events\ApprovalApproved;
use Modules\Validation\Events\ApprovalCompleted;
use Modules\Validation\Events\ApprovalRejected;
use Modules\Validation\Events\ApprovalRequestCreated;
use Modules\Validation\Listeners\NotifyApprovalParticipants;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Approval request events
        ApprovalRequestCreated::class => [
            [NotifyApprovalParticipants::class, 'handleRequestCreated'],
        ],
        ApprovalApproved::class => [
            [NotifyApprovalParticipants::class, 'handleApproved'],
        ],
        ApprovalRejected::class => [
            [NotifyApprovalParticipants::class, 'handleRejected'],
        ],
        ApprovalCompleted::class => [
            [NotifyApprovalParticipants::class, 'handleCompleted'],
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
