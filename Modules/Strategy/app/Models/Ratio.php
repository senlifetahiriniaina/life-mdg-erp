<?php

declare(strict_types=1);

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * Ratio definition — a calculated financial/operational ratio.
 *
 * @property int         $id
 * @property string      $module
 * @property string      $name
 * @property int|null    $numerator_kpi_id
 * @property int|null    $denominator_kpi_id
 * @property string|null $formula
 * @property string|null $benchmark_category
 * @property string|null $description
 * @property string|null $unit
 * @property string      $direction   up|down|target
 * @property float|null  $target_min
 * @property float|null  $target_max
 */
class Ratio extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'strategy_ratios';

    protected $fillable = [
        'module',
        'name',
        'numerator_kpi_id',
        'denominator_kpi_id',
        'formula',
        'benchmark_category',
        'description',
        'unit',
        'direction',
        'target_min',
        'target_max',
    ];

    protected $casts = [
        'target_min' => 'decimal:4',
        'target_max' => 'decimal:4',
    ];

    public function numeratorKpi(): BelongsTo
    {
        return $this->belongsTo(KPI::class, 'numerator_kpi_id');
    }

    public function denominatorKpi(): BelongsTo
    {
        return $this->belongsTo(KPI::class, 'denominator_kpi_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(RatioSnapshot::class, 'ratio_id');
    }
}
