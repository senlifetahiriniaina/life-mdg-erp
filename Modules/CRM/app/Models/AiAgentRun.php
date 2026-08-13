<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CRM\Database\Factories\AiAgentRunFactory;

/**
 * @property int $id
 * @property int $agent_id
 * @property string $entity_type
 * @property int $entity_id
 * @property string $status
 * @property array<string, mixed>|null $result
 * @property string|null $error_message
 * @property Carbon|null $executed_at
 * @property int|null $duration_ms
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AiAgentRun extends Model
{
    use HasFactory;

    protected $table = 'crm_ai_agent_runs';

    protected $fillable = [
        'agent_id',
        'entity_type',
        'entity_id',
        'status',
        'result',
        'error_message',
        'executed_at',
        'duration_ms',
    ];

    protected $casts = [
        'result' => 'array',
        'executed_at' => 'datetime',
    ];

    protected static function newFactory(): AiAgentRunFactory
    {
        return AiAgentRunFactory::new();
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function wasSkipped(): bool
    {
        return $this->status === 'skipped';
    }
}
