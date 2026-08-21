<?php

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ForecastModel extends Model
{
    use HasFactory;

    protected $table = 'forecast_models';

    protected $fillable = [
        'tenant_id',
        'name',
        'module',
        'entity_type',
        'entity_id',
        'algorithm',
        'horizon_days',
        'confidence_level',
        'last_trained_at',
        'next_retrain_at',
        'is_active',
        'config',
    ];

    protected $casts = [
        'config'           => 'array',
        'confidence_level' => 'decimal:4',
        'is_active'        => 'boolean',
        'last_trained_at'  => 'datetime',
        'next_retrain_at'  => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────

    /**
     * Chantier 26: the real migrated FK column on all three of
     * forecast_predictions/forecast_alerts/forecast_scenarios is
     * `forecast_model_id` (NOT NULL, from each table's original create
     * migration) — these relations pointed at `model_id`/`base_model_id`,
     * columns a later additive patch migration bolted on nullable and
     * unused, never the real FK. ForecastPrediction/ForecastAlert/
     * ForecastScenario's own belongsTo() already correctly use
     * `forecast_model_id`; these hasMany() relations now match.
     */
    public function predictions(): HasMany
    {
        return $this->hasMany(ForecastPrediction::class, 'forecast_model_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(ForecastAlert::class, 'forecast_model_id');
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(ForecastScenario::class, 'forecast_model_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeDueForRetraining($query)
    {
        return $query->where('is_active', true)
                     ->where(function ($q) {
                         $q->whereNull('next_retrain_at')
                           ->orWhere('next_retrain_at', '<=', now());
                     });
    }

    // ─── Helpers ──────────────────────────────────────────────────

    public function isAiModel(): bool
    {
        return $this->algorithm === 'ai_claude';
    }

    public function scheduleNextRetrain(): void
    {
        $intervalDays = match ($this->horizon_days) {
            30      => 7,
            60      => 14,
            90      => 30,
            180     => 45,
            default => 60,
        };

        $this->update(['next_retrain_at' => now()->addDays($intervalDays)]);
    }
}
