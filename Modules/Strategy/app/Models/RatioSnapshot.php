<?php

declare(strict_types=1);

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * Historical snapshot of a ratio value for a tenant in a given month.
 *
 * Two coexisting write paths (see 2026_08_16_000010_patch_strategy_ratio_snapshots_columns):
 *  - ratio_id-linked: Ratio::snapshots() relation, `ratio_id` set, `module`/`ratio_key`/`status` null.
 *  - stateless: StrategyRatioService::storeSnapshot()/getHistory(), `module`+`ratio_key` set,
 *    `ratio_id` null (no pre-existing `strategy_ratios` row required).
 *
 * @property int         $id
 * @property int|null    $ratio_id
 * @property string|null $module
 * @property string|null $ratio_key
 * @property string      $tenant_id
 * @property string      $period       YYYY-MM
 * @property float|null  $value
 * @property float|null  $benchmark_value
 * @property float|null  $gap
 * @property string|null $status
 * @property \Carbon\Carbon $created_at
 */
class RatioSnapshot extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'strategy_ratio_snapshots';

    public $timestamps = false;

    protected $fillable = [
        'ratio_id',
        'module',
        'ratio_key',
        'tenant_id',
        'period',
        'value',
        'benchmark_value',
        'gap',
        'status',
        'created_at',
    ];

    protected $casts = [
        'value'           => 'decimal:4',
        'benchmark_value' => 'decimal:4',
        'gap'             => 'decimal:4',
        'created_at'      => 'datetime',
    ];

    public function ratio(): BelongsTo
    {
        return $this->belongsTo(Ratio::class, 'ratio_id');
    }
}
