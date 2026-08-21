<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CRM\Database\Factories\AiAgentFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $trigger_type
 * @property array<string, mixed>|null $trigger_config
 * @property string $action_type
 * @property array<string, mixed>|null $action_config
 * @property array<string, mixed>|null $conditions
 * @property bool $is_active
 * @property Carbon|null $last_run_at
 * @property int $run_count
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AiAgent extends Model
{
    use HasFactory;

    protected $table = 'crm_ai_agents';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'trigger_type',
        'trigger_config',
        'action_type',
        'action_config',
        'conditions',
        'is_active',
        'last_run_at',
        'run_count',
        'created_by',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'action_config' => 'array',
        'conditions' => 'array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    protected static function newFactory(): AiAgentFactory
    {
        return AiAgentFactory::new();
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AiAgentRun::class, 'agent_id');
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function shouldTrigger(string $triggerType): bool
    {
        return $this->trigger_type === $triggerType && $this->is_active;
    }

    public function incrementRunCount(): void
    {
        $this->increment('run_count');
        $this->update(['last_run_at' => now()]);
    }

    public function getConditions(): array
    {
        return $this->conditions ?? [];
    }

    public function getActionConfig(): array
    {
        return $this->action_config ?? [];
    }
}
