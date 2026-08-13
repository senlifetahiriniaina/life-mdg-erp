<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForecastModel extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'crm_forecast_models';

    protected $fillable = [
        'name', 'algorithm', 'description', 'parameters', 'status',
        'accuracy_score', 'training_data_months', 'trained_at', 'last_validated_at', 'features',
    ];

    protected $casts = [
        'parameters'         => 'json',
        'features'           => 'array',
        'trained_at'         => 'datetime',
        'last_validated_at'  => 'datetime',
    ];

    public function inputs(): HasMany
    {
        return $this->hasMany(ForecastInput::class, 'model_id');
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }
}
