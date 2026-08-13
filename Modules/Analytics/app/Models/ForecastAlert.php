<?php

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastAlert extends Model
{
    protected $table = 'forecast_alerts';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'model_id',
        'alert_type',
        'severity',
        'title',
        'message',
        'predicted_date',
        'predicted_value',
        'threshold_value',
        'is_acknowledged',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected $casts = [
        'predicted_date'   => 'date',
        'predicted_value'  => 'decimal:4',
        'threshold_value'  => 'decimal:4',
        'is_acknowledged'  => 'boolean',
        'acknowledged_at'  => 'datetime',
        'created_at'       => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────

    public function forecastModel(): BelongsTo
    {
        return $this->belongsTo(ForecastModel::class, 'model_id');
    }

    // ─── Actions ──────────────────────────────────────────────────

    /**
     * Accuse réception de l'alerte par un utilisateur donné.
     */
    public function acknowledge(int $userId): void
    {
        $this->update([
            'is_acknowledged' => true,
            'acknowledged_by' => $userId,
            'acknowledged_at' => now(),
        ]);
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_acknowledged', false);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
