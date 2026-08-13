<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int    $id
 * @property int    $agent_id
 * @property int    $created_by
 * @property string $goal_type
 * @property string $goal_description
 * @property string $goal_category
 * @property string $metric_name
 * @property int    $baseline_value
 * @property int    $target_value
 * @property string $measurement_unit
 * @property date   $start_date
 * @property date   $end_date
 * @property string $frequency
 * @property int    $weight
 * @property float  $current_progress
 * @property string $status
 * @property string $progress_status
 * @property array  $milestone_dates
 * @property array  $achievements
 * @property string $notes
 * @property \Illuminate\Support\Carbon|null $achieved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class PerformanceGoal extends Model
{
    use HasFactory, SoftDeletes, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_performance_goals';

    protected $fillable = [
        'agent_id',
        'created_by',
        'goal_type',
        'goal_description',
        'goal_category',
        'metric_name',
        'baseline_value',
        'target_value',
        'measurement_unit',
        'start_date',
        'end_date',
        'frequency',
        'weight',
        'current_progress',
        'status',
        'progress_status',
        'milestone_dates',
        'achievements',
        'notes',
        'achieved_at',
    ];

    protected $casts = [
        'baseline_value' => 'integer',
        'target_value' => 'integer',
        'current_progress' => 'decimal:4',
        'start_date' => 'date',
        'end_date' => 'date',
        'achieved_at' => 'datetime',
        'milestone_dates' => 'json',
        'achievements' => 'json',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'agent_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isOnTrack(): bool
    {
        return $this->progress_status === 'on_track';
    }

    public function isBehind(): bool
    {
        return $this->progress_status === 'behind';
    }

    public function isAhead(): bool
    {
        return $this->progress_status === 'ahead';
    }

    public function getProgressPercentage(): float
    {
        return ($this->current_progress * 100);
    }

    public function getExpectedProgress(): float
    {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }

        $totalDays = $this->end_date->diffInDays($this->start_date);
        $elapsedDays = now()->diffInDays($this->start_date);

        if ($totalDays === 0) {
            return 1.0;
        }

        return min($elapsedDays / $totalDays, 1.0);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'current_progress' => 1.0,
            'achieved_at' => now(),
        ]);
    }

    public function updateProgress(float $progress): void
    {
        $expectedProgress = $this->getExpectedProgress();
        $this->update([
            'current_progress' => $progress,
            'progress_status' => match (true) {
                $progress < $expectedProgress => 'behind',
                $progress > $expectedProgress => 'ahead',
                default => 'on_track',
            },
        ]);
    }
}
