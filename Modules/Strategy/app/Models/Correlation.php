<?php

declare(strict_types=1);

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pearson correlation between two KPI time-series.
 *
 * @property int         $id
 * @property string      $kpi_a           module:key pair, e.g. "CRM:conversion_rate"
 * @property string      $kpi_b
 * @property float       $coefficient     -1.0000 to 1.0000
 * @property int         $lag_periods     months kpi_a leads kpi_b
 * @property float       $confidence      0-100
 * @property \Carbon\Carbon|null $last_computed_at
 */
class Correlation extends Model
{
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'strategy_correlations';

    protected $fillable = [
        'kpi_a',
        'kpi_b',
        'coefficient',
        'lag_periods',
        'confidence',
        'last_computed_at',
    ];

    protected $casts = [
        'coefficient'     => 'decimal:4',
        'confidence'      => 'decimal:2',
        'last_computed_at' => 'datetime',
    ];

    public function strengthLabel(): string
    {
        $abs = abs((float) $this->coefficient);
        if ($abs >= 0.8) return 'Très forte';
        if ($abs >= 0.6) return 'Forte';
        if ($abs >= 0.4) return 'Modérée';
        if ($abs >= 0.2) return 'Faible';
        return 'Négligeable';
    }

    public function directionLabel(): string
    {
        return $this->coefficient >= 0 ? 'Positive' : 'Négative';
    }
}
