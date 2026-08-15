<?php

declare(strict_types=1);

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * Key Result Objective — links a StrategyObjective to a KPI with targets.
 *
 * @property int         $id
 * @property int         $objective_id
 * @property int         $kpi_id
 * @property float|null  $target
 * @property float|null  $baseline
 * @property float|null  $current
 * @property float       $weight
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class KRO extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'strategy_kros';

    protected $fillable = [
        'objective_id',
        'kpi_id',
        'target',
        'baseline',
        'current',
        'weight',
    ];

    protected $casts = [
        'target'   => 'decimal:4',
        'baseline' => 'decimal:4',
        'current'  => 'decimal:4',
        'weight'   => 'decimal:2',
    ];

    public function objective(): BelongsTo
    {
        return $this->belongsTo(StrategyObjective::class, 'objective_id');
    }

    public function kpi(): BelongsTo
    {
        return $this->belongsTo(KPI::class, 'kpi_id');
    }

    /**
     * Completion percentage (0–100).
     */
    public function progressPercent(): float
    {
        if ($this->target === null || $this->target == 0) {
            return 0.0;
        }
        $baseline = $this->baseline ?? 0.0;
        $progress = (($this->current ?? $baseline) - $baseline) / ($this->target - $baseline) * 100;
        return max(0.0, min(100.0, $progress));
    }
}
