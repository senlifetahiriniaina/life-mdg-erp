<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Database\Factories\RevenueContractFactory;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $contract_number
 * @property float $contract_amount
 * @property string $currency
 * @property Carbon $contract_start_date
 * @property Carbon $contract_end_date
 * @property string $performance_obligation_type
 * @property string $revenue_recognition_method
 * @property string $status
 * @property float $cumulative_revenue_recognized
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, RevenueRecognitionEvent> $recognitionEvents
 */
class RevenueContract extends Model
{
    use HasFactory;

    protected $table = 'acc_revenue_contracts';

    protected $fillable = [
        'customer_id',
        'contract_number',
        'contract_amount',
        'currency',
        'contract_start_date',
        'contract_end_date',
        'performance_obligation_type',
        'revenue_recognition_method',
        'status',
        'cumulative_revenue_recognized',
    ];

    protected $casts = [
        'contract_amount' => 'encrypted:decimal:2',
        'cumulative_revenue_recognized' => 'encrypted:decimal:2',
        'contract_start_date' => 'datetime',
        'contract_end_date' => 'datetime',
    ];

    protected static function newFactory(): RevenueContractFactory
    {
        return RevenueContractFactory::new();
    }

    public function recognitionEvents(): HasMany
    {
        return $this->hasMany(RevenueRecognitionEvent::class, 'revenue_contract_id');
    }

    public function remainingRevenue(): float
    {
        return (float) $this->contract_amount - (float) $this->cumulative_revenue_recognized;
    }

    public function isComplete(): bool
    {
        return $this->remainingRevenue() <= 0;
    }

    public function percentageComplete(): float
    {
        if ((float) $this->contract_amount === 0) {
            return 0.0;
        }

        return ((float) $this->cumulative_revenue_recognized / (float) $this->contract_amount) * 100;
    }
}
