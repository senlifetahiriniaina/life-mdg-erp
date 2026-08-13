<?php

declare(strict_types=1);

namespace Modules\Workflow\Models\Automation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $flow_id
 * @property int $source_node_id
 * @property int $target_node_id
 * @property string $condition_type  always|if_true|if_false|on_error
 * @property string|null $condition_expr  e.g. "output.amount > 500000"
 */
class AutomationConnection extends Model
{
    use HasFactory;
    protected $table = 'automation_connections';

    protected $fillable = [
        'flow_id',
        'source_node_id',
        'target_node_id',
        'condition_type',
        'condition_expr',
    ];

    // ── Relationships ────────────────────────────────────────────────────────────

    public function flow(): BelongsTo
    {
        return $this->belongsTo(AutomationFlow::class, 'flow_id');
    }

    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(AutomationNode::class, 'source_node_id');
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(AutomationNode::class, 'target_node_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    public function isConditional(): bool
    {
        return $this->condition_type !== 'always';
    }

    public function isErrorPath(): bool
    {
        return $this->condition_type === 'on_error';
    }
}
