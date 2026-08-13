<?php

namespace Modules\Validation\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Validation\Models\ApprovalAction;
use Modules\Validation\Models\ApprovalRequest;

class ApprovalApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ApprovalRequest $request,
        public ApprovalAction $action,
        public User $approver
    ) {}
}
