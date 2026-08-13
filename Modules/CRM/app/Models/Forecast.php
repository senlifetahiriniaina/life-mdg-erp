<?php

declare(strict_types=1);

namespace Modules\CRM\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CRM\Database\Factories\ForecastFactory;

/**
 * @property int $id
 * @property string $period
 * @property int|null $user_id
 * @property string $forecast_amount
 * @property string $commit_amount
 * @property string $best_case
 * @property string $pipeline_total
 * @property string|null $ai_prediction
 * @property int|null $confidence_pct
 * @property Carbon|null $generated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $user
 */
class Forecast extends Model
{
    use HasFactory;

    protected $table = 'crm_forecasts';

    protected $fillable = [
        'period',
        'user_id',
        'forecast_amount',
        'commit_amount',
        'best_case',
        'pipeline_total',
        'ai_prediction',
        'confidence_pct',
        'generated_at',
    ];

    protected $casts = [
        'forecast_amount' => 'decimal:2',
        'commit_amount' => 'decimal:2',
        'best_case' => 'decimal:2',
        'pipeline_total' => 'decimal:2',
        'ai_prediction' => 'decimal:2',
        'confidence_pct' => 'integer',
        'generated_at' => 'datetime',
    ];

    protected static function newFactory(): ForecastFactory
    {
        return ForecastFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
