<?php

namespace Modules\CRM\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\CRM\Database\Factories\ScoringRuleFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $category
 * @property string|null $condition_field
 * @property string|null $condition_operator
 * @property string|null $condition_value
 * @property int $points
 * @property int $weight
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ScoringRule extends Model
{
    use HasFactory;

    protected $table = 'crm_scoring_rules';

    protected $fillable = [
        'name',
        'category',
        'condition_field',
        'condition_operator',
        'condition_value',
        'points',
        'weight',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'points' => 'integer',
        'weight' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function newFactory(): ScoringRuleFactory
    {
        return ScoringRuleFactory::new();
    }

    public function evaluate(mixed $fieldValue): int
    {
        $conditionValue = $this->condition_value;

        $matched = match ($this->condition_operator) {
            'eq' => $fieldValue === $conditionValue,
            'gt' => $fieldValue > $conditionValue,
            'lt' => $fieldValue < $conditionValue,
            'gte' => $fieldValue >= $conditionValue,
            'lte' => $fieldValue <= $conditionValue,
            'contains' => str_contains((string) $fieldValue, (string) $conditionValue),
            'exists' => ! empty($fieldValue),
            default => false,
        };

        return $matched ? $this->points : 0;
    }
}
