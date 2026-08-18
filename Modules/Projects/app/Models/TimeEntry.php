<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Projects\Database\Factories\TimeEntryFactory;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $task_id
 * @property int $user_id
 * @property string|null $description
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int|null $duration_minutes
 * @property float $hourly_rate
 * @property bool $billable
 * @property bool $billed
 * @property int|null $invoice_id
 */
class TimeEntry extends Model
{
    use HasFactory;

    protected static function newFactory(): TimeEntryFactory
    {
        return TimeEntryFactory::new();
    }

    protected $table = 'prj_time_entries';

    protected $fillable = [
        'project_id',
        'task_id',
        'user_id',
        'description',
        'started_at',
        'ended_at',
        'duration_minutes',
        'hourly_rate',
        'billable',
        'billed',
        'invoice_id',
        'timesheet_entry_id',
        'source',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'billable' => 'boolean',
        'billed' => 'boolean',
        'hourly_rate' => 'decimal:2',
        'duration_minutes' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function isBillable(): bool
    {
        return $this->billable === true;
    }

    public function isBilled(): bool
    {
        return $this->billed === true;
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    public function stop(): void
    {
        $this->ended_at = now();
        $this->duration_minutes = (int) ceil($this->started_at->diffInSeconds($this->ended_at) / 60);
        $this->save();
    }

    public function durationInHours(): float
    {
        return ($this->duration_minutes ?? 0) / 60.0;
    }

    public function billableAmount(): float
    {
        return $this->isBillable() ? $this->durationInHours() * (float) $this->hourly_rate : 0.0;
    }

    public function markBilled(): void
    {
        $this->billed = true;
        $this->save();
    }
}
