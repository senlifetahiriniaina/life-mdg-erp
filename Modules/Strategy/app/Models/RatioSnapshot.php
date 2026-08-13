<?php

declare(strict_types=1);

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historical snapshot of a ratio value for a tenant in a given month.
 *
 * @property int         $id
 * @property int         $ratio_id
 * @property string      $tenant_id
 * @property string      $period       YYYY-MM
 * @property float|null  $value
 * @property float|null  $benchmark_value
 * @property float|null  $gap
 * @property \Carbon\Carbon $created_at
 */
class RatioSnapshot extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'strategy_ratio_snapshots';

    public $timestamps = false;

    protected $fillable = [
        'ratio_id',
        'tenant_id',
        'period',
        'value',
        'benchmark_value',
        'gap',
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
