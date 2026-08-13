<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $alert_id
 * @property Carbon $triggered_at
 * @property float $value
 * @property float $threshold
 * @property Carbon|null $resolved_at
 * @property list<string> $notified_channels
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read BiAlert $alert
 */
class BiAlertEvent extends Model
{
    use HasFactory;
    protected $table = 'bi_alert_events';

    protected $fillable = [
        'alert_id',
        'triggered_at',
        'value',
        'threshold',
        'resolved_at',
        'notified_channels',
    ];

    protected $casts = [
        'triggered_at' => 'datetime',
        'resolved_at' => 'datetime',
        'value' => 'float',
        'threshold' => 'float',
        'notified_channels' => 'array',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(BiAlert::class);
    }
}
