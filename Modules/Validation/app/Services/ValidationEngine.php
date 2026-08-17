<?php

declare(strict_types=1);

namespace Modules\Validation\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Validation\Models\ValidationRule;
use Modules\Validation\Models\ValidationRuleSet;
use Modules\Validation\Support\ConditionEvaluator;
use Modules\Validation\Support\ValidationResult;

/**
 * Generic data-validation engine -- distinct in domain from this module's
 * approval-workflow engine (ApprovalRequestService/ApprovalRoutingResolver).
 * The 9 rule types map onto Laravel's own Validator rule strings (already
 * the proven evaluator for email/regex/between/unique/etc, no reason to
 * hand-roll them again) except `required_if`, whose condition check reuses
 * Support\ConditionEvaluator -- the same allow-listed comparison core
 * ApprovalRule uses for its own condition evaluation.
 */
class ValidationEngine
{
    public function createRule(
        string $name,
        string $field,
        string $type,
        array $params = [],
        ?string $message = null
    ): ValidationRule {
        if (! in_array($type, ValidationRule::TYPES, true)) {
            throw new \InvalidArgumentException("Unknown validation rule type: {$type}");
        }

        return ValidationRule::create([
            'name' => $name,
            'field' => $field,
            'type' => $type,
            'params' => $params,
            'message' => $message,
        ]);
    }

    public function createRuleSet(string $name, ?string $description = null): ValidationRuleSet
    {
        return ValidationRuleSet::create([
            'name' => $name,
            'description' => $description,
            'version' => 1,
        ]);
    }

    /**
     * @param  iterable<ValidationRule>  $rules
     */
    public function validate(array $data, iterable $rules): ValidationResult
    {
        $errors = [];

        foreach ($rules as $rule) {
            $error = $this->evaluateRule($rule, $data);
            if ($error !== null) {
                $errors[$rule->field][] = $error;
            }
        }

        return new ValidationResult(empty($errors), $errors);
    }

    public function validateWithRuleSet(array $data, ValidationRuleSet $ruleSet): ValidationResult
    {
        return $this->validate($data, $ruleSet->rules);
    }

    /**
     * @param  ValidationRule[]  $rules
     */
    public function hasCircularDependency(array $rules): bool
    {
        foreach ($rules as $rule) {
            if ($this->hasCycleFrom($rule, [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  int[]  $visitedStack
     */
    private function hasCycleFrom(ValidationRule $rule, array $visitedStack): bool
    {
        if (in_array($rule->id, $visitedStack, true)) {
            return true;
        }

        $visitedStack[] = $rule->id;

        foreach ($rule->dependencies as $dependency) {
            if ($this->hasCycleFrom($dependency, $visitedStack)) {
                return true;
            }
        }

        return false;
    }

    private function evaluateRule(ValidationRule $rule, array $data): ?string
    {
        $field = $rule->field;
        $params = $rule->params ?? [];

        if ($rule->type === 'required_if') {
            $conditionField = $params['condition_field'] ?? null;

            if ($conditionField === null) {
                return null;
            }

            $conditionMet = ConditionEvaluator::compare(
                '==',
                data_get($data, $conditionField),
                $params['condition_value'] ?? null
            );

            if (! $conditionMet) {
                return null;
            }

            return $this->runLaravelRule($rule, $data, $field, 'required');
        }

        $laravelRule = match ($rule->type) {
            'required' => 'required',
            'email' => 'email',
            'confirmed' => 'confirmed',
            'regex' => 'regex:'.($params['pattern'] ?? '/.*/'),
            'min' => 'min:'.($params['value'] ?? $params['min'] ?? 0),
            // Laravel's between/min size checks only treat the value as a
            // number (rather than a string length) when a numeric/integer
            // rule is also present -- without it, '30' between 18 and 65
            // is measured as strlen('30') = 2 and always fails.
            'between' => ['numeric', 'between:'.($params['min'] ?? 0).','.($params['max'] ?? PHP_INT_MAX)],
            'min_items' => 'min:'.($params['min'] ?? 0),
            'unique' => Rule::unique($params['table'] ?? '', $params['column'] ?? $field),
            default => null,
        };

        if ($laravelRule === null) {
            return null;
        }

        return $this->runLaravelRule($rule, $data, $field, $laravelRule);
    }

    private function runLaravelRule(ValidationRule $rule, array $data, string $field, mixed $laravelRule): ?string
    {
        $validator = Validator::make($data, [$field => $laravelRule]);

        if ($validator->fails()) {
            return $rule->message ?? $validator->errors()->first($field);
        }

        return null;
    }
}
