<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowNode extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'crm_workflow_nodes';

    protected $fillable = [
        'workflow_id', 'node_id', 'type', 'name', 'config', 'position_x', 'position_y',
    ];

    protected $casts = [
        'config' => 'json',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function getIsActionAttribute(): bool
    {
        return in_array($this->type, ['action', 'condition']);
    }
}
