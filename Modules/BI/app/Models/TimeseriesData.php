<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                  $id
 * @property int                  $model_id
 * @property \Carbon\Carbon       $recorded_at
 * @property string               $value
 * @property array<string, mixed>|null $dimensions
 * @property bool                 $is_actual
 * @property \Carbon\Carbon       $created_at
 * @property \Carbon\Carbon       $updated_at
 * @property-read ForecastModel   $model
 */
class TimeseriesData extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_timeseries_data';

    protected $fillable = [
        'model_id',
        'recorded_at',
        'value',
        'dimensions',
        'is_actual',
    ];

    protected $casts = [
        'value'       => 'decimal:4',
        'dimensions'  => 'array',
        'is_actual'   => 'boolean',
        'recorded_at' => 'datetime',
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(ForecastModel::class);
    }

    public function getValue(): float
    {
        return (float) $this->value;
    }

    public function getDimensions(): array
    {
        return $this->dimensions ?? [];
    }

    public function isActual(): bool
    {
        return $this->is_actual;
    }
}
