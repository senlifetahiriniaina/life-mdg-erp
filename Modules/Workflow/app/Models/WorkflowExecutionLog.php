<?php

declare(strict_types=1);

namespace Modules\Workflow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $execution_id
 * @property int|null $action_id
 * @property string $status
 * @property array<string,mixed>|null $result
 * @property \Carbon\Carbon|null $executed_at
 */
class WorkflowExecutionLog extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $table = 'wfd_execution_logs';

    protected $fillable = [
        'execution_id',
        'action_id',
        'status',
        'result',
        'executed_at',
    ];

    protected $casts = [
        'result'      => 'array',
        'executed_at' => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class, 'execution_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(WorkflowAction::class, 'action_id');
    }
}
