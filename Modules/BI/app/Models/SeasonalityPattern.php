<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                  $id
 * @property int                  $model_id
 * @property string               $pattern_type
 * @property array<string, mixed> $seasonal_factors
 * @property string               $strength
 * @property \Carbon\Carbon       $created_at
 * @property \Carbon\Carbon       $updated_at
 * @property-read ForecastModel   $model
 */
class SeasonalityPattern extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_seasonality_patterns';

    protected $fillable = [
        'model_id',
        'pattern_type',
        'seasonal_factors',
        'strength',
    ];

    protected $casts = [
        'seasonal_factors' => 'array',
        'strength'         => 'decimal:4',
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(ForecastModel::class);
    }

    public function getPatternTypeLabel(): string
    {
        return match ($this->pattern_type) {
            'daily'   => 'Daily',
            'weekly'  => 'Weekly',
            'monthly' => 'Monthly',
            'yearly'  => 'Yearly',
            default   => ucfirst($this->pattern_type),
        };
    }

    public function getStrength(): float
    {
        return (float) $this->strength;
    }

    public function isStrongSeasonality(): bool
    {
        return (float) $this->strength >= 0.7;
    }

    public function getFactorForPeriod(string $period): float
    {
        $factors = $this->seasonal_factors ?? [];
        return (float) ($factors[$period] ?? 1.0);
    }
}
