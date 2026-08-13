<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                  $id
 * @property int                  $model_id
 * @property string               $trend_slope
 * @property string               $trend_direction
 * @property string               $trend_strength
 * @property int                  $change_points_count
 * @property array<string, mixed>|null $change_point_dates
 * @property \Carbon\Carbon       $created_at
 * @property \Carbon\Carbon       $updated_at
 * @property-read ForecastModel   $model
 */
class TrendAnalysis extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_trend_analysis';

    protected $fillable = [
        'model_id',
        'trend_slope',
        'trend_direction',
        'trend_strength',
        'change_points_count',
        'change_point_dates',
    ];

    protected $casts = [
        'trend_slope'        => 'decimal:4',
        'trend_strength'     => 'decimal:4',
        'change_point_dates' => 'array',
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(ForecastModel::class);
    }

    public function getTrendSlope(): float
    {
        return (float) $this->trend_slope;
    }

    public function getTrendStrength(): float
    {
        return (float) $this->trend_strength;
    }

    public function getTrendDirectionLabel(): string
    {
        return match ($this->trend_direction) {
            'upward'   => 'Upward Trend',
            'downward' => 'Downward Trend',
            'stable'   => 'Stable',
            default    => ucfirst($this->trend_direction),
        };
    }

    public function isStrongTrend(): bool
    {
        return (float) $this->trend_strength >= 0.7;
    }

    public function getChangePoints(): array
    {
        return $this->change_point_dates ?? [];
    }
}
