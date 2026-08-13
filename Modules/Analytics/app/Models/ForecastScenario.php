<?php

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastScenario extends Model
{
    protected $table = 'forecast_scenarios';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'base_model_id',
        'assumptions',
        'results',
        'created_by',
    ];

    protected $casts = [
        'assumptions' => 'array',
        'results'     => 'array',
        'created_at'  => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────

    public function baseModel(): BelongsTo
    {
        return $this->belongsTo(ForecastModel::class, 'base_model_id');
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

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
