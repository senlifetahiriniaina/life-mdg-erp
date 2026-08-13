<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\RecordsActivity;

/**
 * @property int    $id
 * @property int    $ticket_id
 * @property float  $predicted_nps_score
 * @property string $promoter_likelihood
 * @property float  $promoter_probability
 * @property float  $passive_probability
 * @property float  $detractor_probability
 * @property array  $nps_influencing_factors
 * @property string $recommendation_likelihood
 * @property array  $recommendation_sentiment
 * @property float  $advocacy_score
 * @property bool   $is_repeat_customer
 * @property int    $lifetime_value_segment
 * @property string $customer_segment
 * @property array  $churn_risk_indicators
 * @property float  $churn_probability
 * @property int    $actual_nps_score
 * @property \Illuminate\Support\Carbon|null $predicted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class NPSPredictor extends Model
{
    use HasFactory, RecordsActivity;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected static string $auditModule = 'Helpdesk';

    protected $table = 'cs_nps_predictors';

    protected $fillable = [
        'ticket_id',
        'predicted_nps_score',
        'promoter_likelihood',
        'promoter_probability',
        'passive_probability',
        'detractor_probability',
        'nps_influencing_factors',
        'recommendation_likelihood',
        'recommendation_sentiment',
        'advocacy_score',
        'is_repeat_customer',
        'lifetime_value_segment',
        'customer_segment',
        'churn_risk_indicators',
        'churn_probability',
        'actual_nps_score',
        'predicted_at',
    ];

    protected $casts = [
        'predicted_nps_score' => 'decimal:2',
        'promoter_probability' => 'decimal:4',
        'passive_probability' => 'decimal:4',
        'detractor_probability' => 'decimal:4',
        'advocacy_score' => 'decimal:4',
        'is_repeat_customer' => 'boolean',
        'churn_probability' => 'decimal:4',
        'nps_influencing_factors' => 'json',
        'recommendation_sentiment' => 'json',
        'churn_risk_indicators' => 'json',
        'predicted_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function isPromoter(): bool
    {
        return $this->predicted_nps_score >= 9;
    }

    public function isPassive(): bool
    {
        return $this->predicted_nps_score >= 7 && $this->predicted_nps_score <= 8;
    }

    public function isDetractor(): bool
    {
        return $this->predicted_nps_score <= 6;
    }

    public function hasHighChurnRisk(): bool
    {
        return $this->churn_probability >= 0.6;
    }

    public function getCustomerValue(): string
    {
        return match ($this->lifetime_value_segment) {
            1 => 'high',
            2 => 'medium',
            3 => 'low',
            default => 'unknown',
        };
    }

    public function recordActualNPS(int $score): void
    {
        $this->update(['actual_nps_score' => $score]);
    }

    public function getPredictionAccuracy(): float
    {
        if ($this->actual_nps_score === null) {
            return 0;
        }

        return (1 - (abs($this->actual_nps_score - $this->predicted_nps_score) / 100)) * 100;
    }
}
