<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int              $id
 * @property int              $rule_id
 * @property string           $recipient_value
 * @property string           $start_time
 * @property string           $end_time
 * @property string           $days_of_week
 * @property \Carbon\Carbon|null $start_date
 * @property \Carbon\Carbon|null $end_date
 * @property bool             $is_active
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 * @property-read AlertRule   $rule
 */
class DndSchedule extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_dnd_schedules';

    protected $fillable = [
        'rule_id',
        'recipient_value',
        'start_time',
        'end_time',
        'days_of_week',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'is_active'   => 'boolean',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class);
    }

    public function isInDndPeriod(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->start_date && $now->lt($this->start_date)) {
            return false;
        }

        if ($this->end_date && $now->gt($this->end_date)) {
            return false;
        }

        $dayOfWeek = (string) $now->dayOfWeek;
        $activeDays = explode(',', $this->days_of_week);

        if (! in_array($dayOfWeek, $activeDays, true)) {
            return false;
        }

        $currentTime = $now->format('H:i:s');
        return $currentTime >= $this->start_time && $currentTime <= $this->end_time;
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }
}
