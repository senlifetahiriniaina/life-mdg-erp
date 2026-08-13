<?php

declare(strict_types=1);

namespace Modules\Workflow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Workflow chain definition for Phase-39 trigger-based automations.
 *
 * @property int                    $id
 * @property int                    $tenant_id
 * @property string                 $name
 * @property string|null            $description
 * @property string                 $trigger_key      e.g. 'crm.opportunity.won'
 * @property string                 $trigger_module   e.g. 'CRM'
 * @property array<int,array>|null  $conditions       [{field, operator, value}, …]
 * @property array<int,array>       $actions          [{action_key, params, critical}, …]
 * @property bool                   $is_active
 * @property int                    $execution_count
 * @property \Carbon\Carbon|null    $last_executed_at
 * @property \Carbon\Carbon|null    $created_at
 * @property \Carbon\Carbon|null    $updated_at
 * @property \Carbon\Carbon|null    $deleted_at
 */
class WorkflowChainDefinition extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'workflow_chain_definitions';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'trigger_key',
        'trigger_module',
        'conditions',
        'actions',
        'is_active',
        'execution_count',
        'last_executed_at',
    ];

    protected $casts = [
        'conditions'       => 'array',
        'actions'          => 'array',
        'is_active'        => 'boolean',
        'execution_count'  => 'integer',
        'last_executed_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowChainExecution::class, 'workflow_definition_id')->latest();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForTrigger(Builder $query, string $triggerKey): Builder
    {
        return $query->where('trigger_key', $triggerKey);
    }

    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('trigger_module', $module);
    }
}
