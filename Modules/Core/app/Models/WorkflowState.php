<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workflow_definition_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $current_step
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property-read WorkflowDefinition|null $definition
 */
class WorkflowState extends Model
{
    use HasFactory;

    protected $table = 'core_workflow_states';

    protected $fillable = [
        'workflow_definition_id',
        'subject_type',
        'subject_id',
        'current_step',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Return the full step object from the workflow definition for the current step.
     *
     * @return array<string, mixed>|null
     */
    public function getCurrentStep(): ?array
    {
        return $this->definition?->getStepByKey($this->current_step);
    }

    /**
     * Return true when the current step is marked as terminal or completed_at is set.
     */
    public function isCompleted(): bool
    {
        if ($this->completed_at !== null) {
            return true;
        }

        $step = $this->getCurrentStep();

        return ($step['is_terminal'] ?? false) === true;
    }
}
