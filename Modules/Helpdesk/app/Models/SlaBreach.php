<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Helpdesk\Database\Factories\SlaBreachFactory;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $policy_id
 * @property string $breach_type
 * @property Carbon $breached_at
 * @property Carbon|null $acknowledged_at
 * @property int|null $breach_minutes
 * @property bool $escalated
 * @property Carbon|null $escalated_at
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SlaBreach extends Model
{
    use HasFactory;

    protected static function newFactory(): SlaBreachFactory
    {
        return SlaBreachFactory::new();
    }

    protected $table = 'hd_sla_breaches';

    protected $fillable = [
        'ticket_id',
        'policy_id',
        'breach_type',
        'breached_at',
        'acknowledged_at',
        'breach_minutes',
        'escalated',
        'escalated_at',
        'notes',
    ];

    protected $casts = [
        'breached_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'escalated_at' => 'datetime',
        'escalated' => 'boolean',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class, 'policy_id');
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    public function needsEscalation(int $afterMinutes): bool
    {
        return ! $this->escalated && (int) abs(now()->diffInMinutes($this->breached_at)) >= $afterMinutes;
    }

    public function acknowledge(): void
    {
        $this->update(['acknowledged_at' => now()]);
    }

    public function escalate(): void
    {
        $this->update([
            'escalated' => true,
            'escalated_at' => now(),
        ]);
    }
}
