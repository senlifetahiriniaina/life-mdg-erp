<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastInput extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'crm_forecast_inputs';

    protected $fillable = [
        'forecast_id', 'model_id', 'input_name', 'value', 'unit', 'data_source',
    ];

    protected $casts = [
        'value' => 'decimal:2',
    ];

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(Forecast::class, 'forecast_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(ForecastModel::class, 'model_id');
    }
}
