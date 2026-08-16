<?php

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ForecastScenario extends Model
{
    use HasFactory;

    protected $table = 'forecast_scenarios';

    protected $fillable = [
        'forecast_model_id',
        'name',
        'description',
        'assumptions',
        'results',
        'status',
    ];

    protected $casts = [
        'assumptions' => 'array',
        'results'     => 'array',
        'created_at'  => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────

    public function forecastModel(): BelongsTo
    {
        return $this->belongsTo(ForecastModel::class, 'forecast_model_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────

    /**
     * Retourne le résumé chiffré du scénario (valeur totale prévue sur l'horizon).
     */
    public function getTotalPredicted(): float
    {
        $predictions = $this->results['predictions'] ?? [];

        return (float) array_sum(array_column($predictions, 'value'));
    }

    /**
     * forecast_scenarios has no tenant_id column — scoped through its
     * forecast_model_id, which does belong to a tenant.
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->whereHas('forecastModel', fn ($q) => $q->where('tenant_id', $tenantId));
    }
}
