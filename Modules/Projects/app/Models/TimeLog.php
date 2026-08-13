<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $task_id
 * @property int $user_id
 * @property float $hours
 * @property Carbon $date
 * @property string|null $description
 * @property bool $is_billable
 * @property float|null $hourly_rate
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TimeLog extends Model
{
    use HasFactory;
    protected $table = 'prj_time_logs';

    protected $fillable = [
        'task_id', 'user_id', 'hours', 'date', 'description', 'is_billable', 'hourly_rate',
    ];

    protected $casts = [
        'date' => 'date',
        'hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'is_billable' => 'boolean',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
