<?php

namespace Modules\Validation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Validation\Models\ApprovalRequest;

class ApprovalCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ApprovalRequest $request,
        public bool $approved
    ) {}
}
