<?php

namespace Modules\Accounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\TreasuryAlertFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $threshold_amount
 * @property int $days_lookahead
 * @property string $severity
 * @property bool $is_active
 * @property array<string, mixed>|null $notification_channels
 * @property Carbon|null $last_triggered_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $createdBy
 */
class TreasuryAlert extends Model
{
    use HasFactory;

    protected static function newFactory(): TreasuryAlertFactory
    {
        return TreasuryAlertFactory::new();
    }

    protected $table = 'acc_treasury_alerts';

    protected $fillable = [
        'name',
        'type',
        'threshold_amount',
        'days_lookahead',
        'severity',
        'is_active',
        'notification_channels',
        'last_triggered_at',
        'created_by',
    ];

    protected $casts = [
        'threshold_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
        'notification_channels' => 'json',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isTriggered(float $balance, int $days): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($days > $this->days_lookahead) {
            return false;
        }

        $threshold = (float) $this->threshold_amount;

        return match ($this->type) {
            'low_balance' => $balance < $threshold,
            'high_balance' => $balance > $threshold,
            'large_outflow' => $balance < -$threshold,
            'negative_forecast' => $balance < 0,
            default => false,
        };
    }
}
