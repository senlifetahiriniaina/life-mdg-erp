<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int|null $widget_id
 * @property int|null $query_id
 * @property string $condition_type
 * @property float $threshold
 * @property string $metric_name
 * @property int $check_interval_minutes
 * @property list<string> $channels
 * @property list<int> $recipients
 * @property string $status
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $last_triggered_at
 * @property float|null $last_value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Widget|null         $widget
 * @property-read BiQuery|null        $biQuery
 * @property-read Collection<int, BiAlertEvent> $events
 */
class BiAlert extends Model
{
    use HasFactory;
    protected $table = 'bi_alerts';

    protected $fillable = [
        'name',
        'widget_id',
        'query_id',
        'condition_type',
        'threshold',
        'metric_name',
        'check_interval_minutes',
        'channels',
        'recipients',
        'status',
        'last_checked_at',
        'last_triggered_at',
        'last_value',
    ];

    protected $casts = [
        'threshold' => 'float',
        'last_value' => 'float',
        'check_interval_minutes' => 'integer',
        'channels' => 'array',
        'recipients' => 'array',
        'last_checked_at' => 'datetime',
        'last_triggered_at' => 'datetime',
    ];

    public function widget(): BelongsTo
    {
        return $this->belongsTo(Widget::class);
    }

    public function biQuery(): BelongsTo
    {
        // Chantier 32.24 (BI 14-layer audit, layer 10 — relational): Laravel's
        // default FK convention for a `biQuery()` relation is `bi_query_id`, but
        // the real column (per $fillable/migration) is `query_id` — the implicit
        // default silently resolved to null on every real alert, confirmed
        // empirically via tinker (AlertService::refreshValue() always got a null
        // $query even when a real query_id was set). Explicit FK fixes it.
        return $this->belongsTo(BiQuery::class, 'query_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BiAlertEvent::class, 'alert_id');
    }
}
