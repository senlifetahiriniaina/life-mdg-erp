<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $agent_id
 * @property int|null $created_by
 * @property array|null $goals
 * @property array|null $focus_areas
 * @property int $duration_months
 * @property array|null $milestones
 * @property int $milestones_completed
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property string|null $notes
 */
class DevelopmentPlan extends Model
{
    use HasFactory;

    protected $table = 'hd_development_plans';

    protected $fillable = [
        'agent_id',
        'created_by',
        'goals',
        'focus_areas',
        'duration_months',
        'milestones',
        'milestones_completed',
        'status',
        'start_date',
        'end_date',
        'notes',
    ];

    protected $casts = [
        'goals' => 'array',
        'focus_areas' => 'array',
        'milestones' => 'array',
        'duration_months' => 'integer',
        'milestones_completed' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
