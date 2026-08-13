<?php

declare(strict_types=1);

namespace Modules\Workflow\Models\Automation;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $flow_id
 * @property string $node_type  trigger|action|condition|transform|delay|loop|sub_flow|webhook_out|ai_action
 * @property string $node_key   e.g. 'crm.opportunity.won'
 * @property string $label
 * @property int $position_x
 * @property int $position_y
 * @property array<string,mixed>|null $config
 * @property array<string,mixed>|null $input_schema
 * @property array<string,mixed>|null $output_schema
 * @property string $error_handling  skip|retry|stop|notify
 */
class AutomationNode extends Model
{
    use HasFactory;
    protected $table = 'automation_nodes';

    protected $fillable = [
        'flow_id',
        'node_type',
        'node_key',
        'label',
        'position_x',
        'position_y',
        'config',
        'input_schema',
        'output_schema',
        'error_handling',
    ];

    protected $casts = [
        'config'        => 'array',
        'input_schema'  => 'array',
        'output_schema' => 'array',
        'position_x'    => 'integer',
        'position_y'    => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────────

    public function flow(): BelongsTo
    {
        return $this->belongsTo(AutomationFlow::class, 'flow_id');
    }

    public function outgoingConnections(): HasMany
    {
        return $this->hasMany(AutomationConnection::class, 'source_node_id');
    }

    public function incomingConnections(): HasMany
    {
        return $this->hasMany(AutomationConnection::class, 'target_node_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────────

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('node_type', $type);
    }

    public function scopeWithKey(Builder $query, string $key): Builder
    {
        return $query->where('node_key', $key);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    public function isTrigger(): bool
    {
        return $this->node_type === 'trigger';
    }

    public function isCondition(): bool
    {
        return $this->node_type === 'condition';
    }

    public function getNextNodes(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->outgoingConnections()
            ->with('targetNode')
            ->get()
            ->pluck('targetNode');
    }
}
