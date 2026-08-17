<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\BI\Database\Factories\KpiFactory;

class Kpi extends Model
{
    use HasFactory;

    protected $table = 'bi_kpis';

    protected $fillable = [
        'name',
        'code',
        'description',
        'category',
        'formula',
        'metric',
        'source_module',
        'current_value',
        'target_value',
        'threshold_warning',
        'threshold_critical',
        'value',
        'target',
        'unit',
        'period',
        'module',
        'trend',
        'is_active',
        'last_calculated_at',
    ];

    protected $casts = [
        'current_value' => 'decimal:4',
        'target_value' => 'decimal:4',
        'threshold_warning' => 'decimal:4',
        'threshold_critical' => 'decimal:4',
        'value' => 'decimal:4',
        'target' => 'decimal:4',
        'is_active' => 'boolean',
        'last_calculated_at' => 'datetime',
    ];

    protected static function newFactory(): KpiFactory
    {
        return KpiFactory::new();
    }

    public function history(): HasMany
    {
        return $this->hasMany(KpiHistory::class);
    }
}
