<?php

declare(strict_types=1);

namespace Modules\Workflow\Models;

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
 * @property string $module
 * @property string $trigger_event
 * @property array<string,mixed>|null $trigger_conditions
 * @property bool $is_active
 * @property int|null $created_by
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class WorkflowDefinition extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'wfd_definitions';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'module',
        'trigger_event',
        'trigger_conditions',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'trigger_conditions' => 'array',
        'is_active'          => 'boolean',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class, 'workflow_id')->orderBy('order');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class, 'workflow_id')->latest();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }
}
