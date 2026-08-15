<?php

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ForecastPrediction extends Model
{
    use HasFactory;

    protected $table = 'forecast_predictions';

    public $timestamps = false;

    protected $fillable = [
        'model_id',
        'tenant_id',
        'forecast_date',
        'predicted_value',
        'lower_bound',
        'predicted_upper_bound',
        'actual_value',
        'error_pct',
        'confidence',
        'metadata',
    ];

    protected $casts = [
        'forecast_date'         => 'date',
        'predicted_value'       => 'decimal:4',
        'lower_bound'           => 'decimal:4',
        'predicted_upper_bound' => 'decimal:4',
        'actual_value'          => 'decimal:4',
        'error_pct'             => 'decimal:4',
        'confidence'            => 'decimal:4',
        'metadata'              => 'array',
        'created_at'            => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────

    public function model(): BelongsTo
    {
        return $this->belongsTo(ForecastModel::class, 'model_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────

    /**
     * Indique si la prévision est précise (erreur < 15 %).
     */
    public function isAccurate(): bool
    {
        if ($this->error_pct === null) {
            return true; // pas encore évalué — optimiste par défaut
        }

        return (float) $this->error_pct < 15.0;
    }

    /**
     * Indique si la valeur réelle est disponible pour comparaison.
     */
    public function hasActual(): bool
    {
        return $this->actual_value !== null;
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeFuture($query)
    {
        return $query->where('forecast_date', '>', now()->toDateString());
    }

    public function scopePast($query)
    {
        return $query->where('forecast_date', '<=', now()->toDateString());
    }

    public function scopeUnfilled($query)
    {
        return $query->whereNull('actual_value')
                     ->where('forecast_date', '<=', now()->toDateString());
    }
}
