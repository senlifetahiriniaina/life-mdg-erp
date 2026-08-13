<?php

namespace Modules\Validation\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected $fillable = [
        'workflow_id',
        'rule_order',
        'condition_type',
        'condition_value',
        'required_approvers_count',
        'approval_mode',
        'status',
    ];

    protected $casts = [
        'required_approvers_count' => 'integer',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function evaluateCondition($value): bool
    {
        // Example: condition_type = 'amount', condition_value = '>1000'
        // This is a simplified version; extend based on business logic
        if ($this->condition_type === 'amount') {
            return eval("return {$value} {$this->condition_value};");
        }

        return true;
    }
}
