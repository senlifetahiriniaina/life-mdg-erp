<?php

declare(strict_types=1);

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApprovalLog extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'hr_leave_approval_log';

    public $timestamps = false;

    protected $fillable = [
        'leave_request_id',
        'level',
        'approver_id',
        'approver_role',
        'action',
        'comment',
        'actioned_at',
        'created_at',
    ];

    protected $casts = [
        'actioned_at' => 'datetime',
        'created_at'  => 'datetime',
        'level'       => 'integer',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }
}
