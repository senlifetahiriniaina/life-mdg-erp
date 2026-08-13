<?php

namespace Modules\Validation\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Validation\Events\ApprovalApproved;
use Modules\Validation\Events\ApprovalCompleted;
use Modules\Validation\Events\ApprovalRejected;
use Modules\Validation\Events\ApprovalRequestCreated;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Approval request events
        ApprovalRequestCreated::class => [
            // Event listeners will be added as they're created
        ],
        ApprovalApproved::class => [
            // Event listeners
        ],
        ApprovalRejected::class => [
            // Event listeners
        ],
        ApprovalCompleted::class => [
            // Event listeners
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
