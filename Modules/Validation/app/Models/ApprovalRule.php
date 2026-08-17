<?php

namespace Modules\Validation\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Validation\Support\ConditionEvaluator;

/**
 * @property int $id
 * @property int $workflow_id
 * @property int $rule_order
 * @property string $condition_type
 * @property string|null $condition_value
 * @property int $required_approvers_count
 * @property string $approval_mode
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ApprovalRule extends Model
{
    use HasFactory;
    protected $table = 'validation_approval_rules';

    /** Condition types an admin can currently configure a rule with. */
    public const CONDITION_TYPES = ['amount', 'category', 'department', 'custom_field'];

    /** Allow-listed comparison operators — never parsed/eval'd from a DB string. */
    public const OPERATORS = ['>', '<', '>=', '<=', '==', '!='];

    protected $fillable = [
        'workflow_id',
        'rule_order',
        'condition_type',
        'condition_operator',
        'condition_value',
        'condition_field',
        'required_approvers_count',
        'approval_mode',
        'hierarchy_id',
        'status',
    ];

    protected $casts = [
        'required_approvers_count' => 'integer',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function hierarchy(): BelongsTo
    {
        return $this->belongsTo(ApprovalHierarchy::class, 'hierarchy_id');
    }

    /**
     * Evaluate whether this rule applies to the given approvable model.
     * Never uses eval() — condition_operator is a write-time-validated enum
     * (see ApprovalRuleController), and condition_type dispatches to a fixed
     * set of comparison methods below.
     */
    public function evaluateCondition($approvable): bool
    {
        return match ($this->condition_type) {
            'amount' => $this->evaluateAmount($approvable),
            'category' => $this->evaluateEquals(data_get($approvable, 'category')),
            'department' => $this->evaluateEquals(data_get($approvable, 'department_id')),
            'custom_field' => $this->evaluateEquals(data_get($approvable, $this->condition_field ?? '')),
            default => true,
        };
    }

    protected function evaluateAmount($approvable): bool
    {
        $amount = (float) (data_get($approvable, 'total') ?? data_get($approvable, 'amount') ?? 0);
        $threshold = (float) $this->condition_value;

        return ConditionEvaluator::compare($this->condition_operator, $amount, $threshold);
    }

    protected function evaluateEquals($actual): bool
    {
        return ConditionEvaluator::compare('==', $actual, $this->condition_value);
    }
}
