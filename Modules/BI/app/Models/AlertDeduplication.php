<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int              $id
 * @property int              $rule_id
 * @property string           $grouping_key
 * @property int              $grouped_count
 * @property \Carbon\Carbon   $first_triggered_at
 * @property \Carbon\Carbon   $last_triggered_at
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 * @property-read AlertRule   $rule
 */
class AlertDeduplication extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_alert_deduplication';

    protected $fillable = [
        'rule_id',
        'grouping_key',
        'grouped_count',
        'first_triggered_at',
        'last_triggered_at',
    ];

    protected $casts = [
        'first_triggered_at' => 'datetime',
        'last_triggered_at'  => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class);
    }

    public function incrementGroupCount(): void
    {
        $this->increment('grouped_count');
        $this->update(['last_triggered_at' => now()]);
    }

    public function getGroupedCount(): int
    {
        return $this->grouped_count;
    }

    public function getSecondsSinceFirstTrigger(): int
    {
        return (int) $this->first_triggered_at->diffInSeconds(now());
    }
}
