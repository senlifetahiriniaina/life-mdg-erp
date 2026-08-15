<?php

declare(strict_types=1);

namespace Modules\Strategy\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * Strategic alert triggered when a KPI or ratio deviates from its benchmark.
 *
 * @property int         $id
 * @property string      $tenant_id
 * @property string      $type
 * @property string      $severity     info|warning|critical
 * @property string      $message
 * @property int|null    $kpi_id
 * @property int|null    $ratio_id
 * @property \Carbon\Carbon $triggered_at
 * @property \Carbon\Carbon|null $resolved_at
 */
class StrategicAlert extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;
    protected $table = 'strategy_alerts';

    protected $fillable = [
        'tenant_id',
        'type',
        'severity',
        'message',
        'kpi_id',
        'ratio_id',
        'triggered_at',
        'resolved_at',
    ];

    protected $casts = [
        'triggered_at' => 'datetime',
        'resolved_at'  => 'datetime',
    ];

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function resolve(): void
    {
        $this->update(['resolved_at' => now()]);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
