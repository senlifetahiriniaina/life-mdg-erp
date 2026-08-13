<?php

namespace Modules\CRM\Models;

use App\Traits\AuditableActions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $trigger_type
 * @property int $owner_id
 * @property string $status
 * @property array|null $trigger_config
 * @property int $execution_count
 * @property int $success_count
 * @property int $failure_count
 */
class Workflow extends Model
{
    use HasFactory, SoftDeletes, RecordsActivity, AuditableActions;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'crm_workflows';

    protected string $auditModule = 'CRM';
    protected array $auditableFields = ['status', 'name', 'trigger_type'];

    protected $fillable = [
        'name', 'description', 'trigger_type', 'owner_id', 'status',
        'trigger_config', 'execution_count', 'success_count', 'failure_count',
    ];

    protected $casts = [
        'trigger_config' => 'json',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(WorkflowNode::class, 'workflow_id');
    }

    public function edges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class, 'workflow_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class, 'workflow_id');
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->execution_count === 0) {
            return 0;
        }

        return ($this->success_count / $this->execution_count) * 100;
    }
}
