<?php

declare(strict_types=1);

namespace Modules\Workflow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $workflow_id
 * @property int $order
 * @property string $action_type
 * @property array<string,mixed>|null $action_config
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class WorkflowAction extends Model
{
    use HasFactory;
    protected $table = 'wfd_actions';

    protected $fillable = [
        'workflow_id',
        'order',
        'action_type',
        'action_config',
    ];

    protected $casts = [
        'action_config' => 'array',
        'order'         => 'integer',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_id');
    }

    public function executionLogs(): HasMany
    {
        return $this->hasMany(WorkflowExecutionLog::class, 'action_id');
    }
}
