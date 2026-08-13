<?php

namespace Modules\Validation\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Validation\Models\ApprovalRequest;

class ApprovalRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ApprovalRequest $request,
        public string $reason,
        public User $rejectedBy
    ) {}
}
