<?php

declare(strict_types=1);

namespace Modules\Validation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A generic, data-shaped validation rule -- distinct from ApprovalRule,
 * which routes multi-level approval workflows. This one validates request
 * payloads against a fixed set of rule types, each mapping onto Laravel's
 * own Validator rules except `required_if`, whose condition check reuses
 * Support\ConditionEvaluator.
 */
class ValidationRule extends Model
{
    use HasFactory;

    protected $table = 'validation_rules';

    /** Allow-listed rule types this engine knows how to evaluate. */
    public const TYPES = [
        'required',
        'email',
        'required_if',
        'confirmed',
        'regex',
        'min',
        'between',
        'min_items',
        'unique',
    ];

    protected $fillable = [
        'name',
        'field',
        'type',
        'params',
        'message',
    ];

    protected $casts = [
        'params' => 'array',
    ];

    public function ruleSets(): BelongsToMany
    {
        return $this->belongsToMany(ValidationRuleSet::class, 'validation_rule_set_rule', 'rule_id', 'rule_set_id');
    }

    /**
     * Rules this rule depends on (must be satisfied/evaluated before it).
     */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'validation_rule_dependencies', 'rule_id', 'depends_on_rule_id');
    }

    public function dependsOn(ValidationRule $other): void
    {
        $this->dependencies()->syncWithoutDetaching([$other->id]);
    }
}
