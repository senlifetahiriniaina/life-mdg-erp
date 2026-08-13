<?php

declare(strict_types=1);

namespace Modules\BI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int              $id
 * @property int              $rule_id
 * @property int              $condition_order
 * @property string           $operator
 * @property string           $value
 * @property string           $comparison_type
 * @property int|null         $lookback_period
 * @property string           $logic_operator
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 * @property-read AlertRule   $rule
 */
class AlertCondition extends Model
{
    use HasFactory;
    use \Modules\AuditLog\Traits\HasAuditLog;

    protected $table = 'bi_alert_conditions';

    protected $fillable = [
        'rule_id',
        'condition_order',
        'operator',
        'value',
        'comparison_type',
        'lookback_period',
        'logic_operator',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class);
    }

    public function evaluate(float $currentValue): bool
    {
        return match ($this->operator) {
            'greater_than'  => $currentValue > (float) $this->value,
            'less_than'     => $currentValue < (float) $this->value,
            'equals'        => $currentValue === (float) $this->value,
            'range'         => $this->evaluateRange($currentValue),
            'contains'      => str_contains((string) $currentValue, $this->value),
            default         => false,
        };
    }

    private function evaluateRange(float $currentValue): bool
    {
        $parts = explode(',', $this->value);
        if (count($parts) !== 2) {
            return false;
        }
        $min = (float) trim($parts[0]);
        $max = (float) trim($parts[1]);
        return $currentValue >= $min && $currentValue <= $max;
    }

    public function getOperatorLabel(): string
    {
        return match ($this->operator) {
            'greater_than'  => 'Greater Than',
            'less_than'     => 'Less Than',
            'equals'        => 'Equals',
            'range'         => 'Range',
            'contains'      => 'Contains',
            default         => $this->operator,
        };
    }
}
