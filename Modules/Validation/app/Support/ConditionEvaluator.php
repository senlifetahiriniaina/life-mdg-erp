<?php

declare(strict_types=1);

namespace Modules\Validation\Support;

/**
 * Single allow-listed comparison core shared by the approval engine
 * (ApprovalRule::evaluateAmount()/evaluateEquals()) and the generic
 * ValidationEngine's `required_if` condition check, so the two domains
 * (approval routing vs. data validation) don't each grow their own
 * near-identical operator-matching logic.
 */
class ConditionEvaluator
{
    /** Never parsed/eval'd from a string — a fixed match() arm per operator. */
    public const OPERATORS = ['>', '<', '>=', '<=', '==', '!='];

    public static function compare(string $operator, mixed $actual, mixed $expected): bool
    {
        if (is_numeric($actual) && is_numeric($expected)) {
            $actual = (float) $actual;
            $expected = (float) $expected;
        }

        return match ($operator) {
            '>' => $actual > $expected,
            '<' => $actual < $expected,
            '>=' => $actual >= $expected,
            '<=' => $actual <= $expected,
            '==' => (string) $actual === (string) $expected,
            '!=' => (string) $actual !== (string) $expected,
            default => true,
        };
    }
}
