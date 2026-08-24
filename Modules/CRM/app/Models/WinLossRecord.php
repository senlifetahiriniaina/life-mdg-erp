<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\CRM\Database\Factories\WinLossRecordFactory;

/**
 * @property int $id
 * @property int $opportunity_id
 * @property string $outcome
 * @property string|null $reason
 * @property string|null $competitor
 * @property string $deal_value
 * @property int|null $sales_cycle_days
 * @property int|null $recorded_by
 * @property Carbon|null $recorded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WinLossRecord extends Model
{
    use HasFactory;

    protected static function newFactory(): WinLossRecordFactory
    {
        return WinLossRecordFactory::new();
    }

    protected $table = 'crm_win_loss_records';

    protected $fillable = [
        'tenant_id',
        'opportunity_id',
        'outcome',
        'reason',
        'competitor',
        'deal_value',
        'sales_cycle_days',
        'recorded_by',
        'recorded_at',
    ];

    protected $casts = [
        'deal_value' => 'decimal:4',
        'recorded_at' => 'datetime',
    ];

    public function isWon(): bool
    {
        return $this->outcome === 'won';
    }

    public function isLost(): bool
    {
        return $this->outcome === 'lost';
    }
}
