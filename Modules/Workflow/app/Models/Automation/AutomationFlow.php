<?php

declare(strict_types=1);

namespace Modules\Workflow\Models\Automation;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $description
 * @property string $icon
 * @property string $color
 * @property bool $is_active
 * @property string $trigger_type  webhook|schedule|module_event|manual
 * @property array<string,mixed>|null $trigger_config
 * @property array<string>|null $tags
 * @property int $version
 * @property int $version_number
 * @property bool $is_published
 * @property int|null $parent_version_id
 * @property int|null $created_by
 * @property \Carbon\Carbon|null $last_run_at
 * @property int $total_runs
 * @property int $success_runs
 */
class AutomationFlow extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'automation_flows';

    protected $fillable = [
        'tenant_id',
        'key',
        'name',
        'description',
        'trigger',
        'icon',
        'color',
        'is_active',
        'status',
        'trigger_type',
        'trigger_config',
        'nodes',
        'edges',
        'tags',
        'version',
        'version_number',
        'is_published',
        'parent_version_id',
        'created_by',
        'last_run_at',
        'total_runs',
        'success_runs',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'tags'           => 'array',
        'is_active'          => 'boolean',
        'is_published'       => 'boolean',
        'last_run_at'        => 'datetime',
        'total_runs'         => 'integer',
        'success_runs'       => 'integer',
        'version'            => 'integer',
        'version_number'     => 'integer',
        'parent_version_id'  => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────────

    public function nodes(): HasMany
    {
        return $this->hasMany(AutomationNode::class, 'flow_id');
    }

    public function connections(): HasMany
    {
        return $this->hasMany(AutomationConnection::class, 'flow_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(AutomationExecution::class, 'flow_id')->latest();
    }

    public function variables(): HasMany
    {
        return $this->hasMany(AutomationVariable::class, 'flow_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(\Modules\Workflow\Models\Automation\FlowVersion::class, 'flow_id')
            ->orderByDesc('version_number');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByTriggerType(Builder $query, string $type): Builder
    {
        return $query->where('trigger_type', $type);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    public function getTriggerNode(): ?AutomationNode
    {
        return $this->nodes()->where('node_type', 'trigger')->first();
    }

    public function incrementRuns(bool $success): void
    {
        $this->increment('total_runs');
        if ($success) {
            $this->increment('success_runs');
        }
        $this->update(['last_run_at' => now()]);
    }

    public function successRate(): float
    {
        if ($this->total_runs === 0) {
            return 0.0;
        }
        return round($this->success_runs / $this->total_runs * 100, 2);
    }
}
