<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects passwords that satisfy the basic complexity rules but are still weak
 * in practice: common base words, repeated characters, or simple sequences.
 *
 * Complements Laravel's built-in Password rule (length / mixed-case / numbers /
 * symbols) with a small blocklist and pattern checks.
 */
class StrongPassword implements ValidationRule
{
    /** Common weak base words that frequently appear in breached passwords. */
    private const COMMON = [
        'password', 'motdepasse', 'azerty', 'qwerty', 'letmein',
        'changeme', 'iloveyou',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.string')->translate();

            return;
        }

        $lower = strtolower($value);

        foreach (self::COMMON as $word) {
            if (str_contains($lower, $word)) {
                $fail('The :attribute contains a commonly used and easily guessed term.')->translate();

                return;
            }
        }

        // All-identical characters (e.g. "aaaaaaaaaaaa").
        if (preg_match('/^(.)\1+$/', $value)) {
            $fail('The :attribute must not be a single repeated character.')->translate();

            return;
        }

        // Simple ascending/descending runs (e.g. "123456", "abcdef").
        if ($this->hasSequentialRun($lower, 6)) {
            $fail('The :attribute must not contain a long sequential run of characters.')->translate();
        }
    }

    /**
     * Detect a sequential run (ascending or descending by 1) of the given length.
     */
    private function hasSequentialRun(string $value, int $length): bool
    {
        $run = 1;
        $direction = 0;

        for ($i = 1, $len = strlen($value); $i < $len; $i++) {
            $diff = ord($value[$i]) - ord($value[$i - 1]);

            if (($diff === 1 || $diff === -1) && ($direction === 0 || $direction === $diff)) {
                $direction = $diff;
                $run++;

                if ($run >= $length) {
                    return true;
                }
            } else {
                $run = 1;
                $direction = 0;
            }
        }

        return false;
    }
}
