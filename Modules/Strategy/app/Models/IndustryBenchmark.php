<?php

declare(strict_types=1);

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * Industry benchmark percentile data for a given ratio.
 *
 * @property int         $id
 * @property string      $ratio_name
 * @property string      $industry
 * @property string      $country       ISO alpha-2 or 'WW'
 * @property float|null  $p25
 * @property float|null  $median
 * @property float|null  $p75
 * @property int         $year
 * @property string|null $source
 */
class IndustryBenchmark extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'strategy_industry_benchmarks';

    protected $fillable = [
        'ratio_name',
        'industry',
        'country',
        'p25',
        'median',
        'p75',
        'year',
        'source',
    ];

    protected $casts = [
        'p25'    => 'decimal:4',
        'median' => 'decimal:4',
        'p75'    => 'decimal:4',
        'year'   => 'integer',
    ];
}
