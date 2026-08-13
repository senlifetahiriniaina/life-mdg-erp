<?php

namespace Modules\Validation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalWorkflow;

class ApprovalRequestCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ApprovalRequest $request,
        public ApprovalWorkflow $workflow
    ) {}
}
