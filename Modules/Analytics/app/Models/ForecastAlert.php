<?php

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastAlert extends Model
{
    use HasFactory;

    protected $table = 'forecast_alerts';

    protected $fillable = [
        'forecast_model_id',
        'alert_type',
        'severity',
        'message',
        'context',
        'status',
        'triggered_at',
        'resolved_at',
    ];

    protected $casts = [
        'context'      => 'array',
        'triggered_at' => 'datetime',
        'resolved_at'  => 'datetime',
        'created_at'   => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────

    public function forecastModel(): BelongsTo
    {
        return $this->belongsTo(ForecastModel::class, 'forecast_model_id');
    }

    // ─── Actions ──────────────────────────────────────────────────

    /**
     * Accuse réception de l'alerte par un utilisateur donné.
     * No dedicated acknowledged_by column exists — recorded in context.
     */
    public function acknowledge(int $userId): void
    {
        $this->update([
            'status'      => 'acknowledged',
            'resolved_at' => now(),
            'context'     => array_merge($this->context ?? [], ['acknowledged_by' => $userId]),
        ]);
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * forecast_alerts has no tenant_id column — scoped through its
     * forecast_model_id, which does belong to a tenant.
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->whereHas('forecastModel', fn ($q) => $q->where('tenant_id', $tenantId));
    }
}
