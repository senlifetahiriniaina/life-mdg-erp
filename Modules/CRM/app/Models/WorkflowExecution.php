<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkflowExecution extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'crm_workflow_executions';

    protected $fillable = [
        'workflow_id', 'subject_type', 'subject_id', 'status',
        'started_at', 'completed_at', 'execution_trace', 'error_message',
    ];

    protected $casts = [
        'execution_trace' => 'json',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function getDurationAttribute(): int
    {
        if (!$this->completed_at) {
            return 0;
        }

        return $this->completed_at->diffInSeconds($this->started_at);
    }
}
