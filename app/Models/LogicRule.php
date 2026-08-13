<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogicRule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'trigger',
        'conditions',
        'actions',
        'is_enabled',
        'created_by',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'is_enabled' => 'boolean',
        'last_executed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(LogicRuleExecution::class, 'rule_id');
    }

    public function disable(): void
    {
        $this->update(['is_enabled' => false]);
    }

    public function enable(): void
    {
        $this->update(['is_enabled' => true]);
    }

    public function recordExecution(bool $conditionsMet, array $actionsExecuted, ?string $error = null, int $durationMs = 0): LogicRuleExecution
    {
        $this->increment('execution_count');
        $this->update(['last_executed_at' => now()]);

        return $this->executions()->create([
            'trigger_data' => [],
            'conditions_met' => $conditionsMet,
            'actions_executed' => $actionsExecuted,
            'error_message' => $error,
            'duration_ms' => $durationMs,
            'executed_at' => now(),
        ]);
    }

    public function getConditionsAttribute($value): array
    {
        return json_decode($value, true) ?? [];
    }

    public function getActionsAttribute($value): array
    {
        return json_decode($value, true) ?? [];
    }
}
