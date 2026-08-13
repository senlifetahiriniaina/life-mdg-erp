<?php

declare(strict_types=1);

namespace Modules\Shared\Exceptions;

class ValidationException extends BaseException
{
    protected string $errorCode = 'VALIDATION_ERROR';

    public static function missingRequired(string $field, array $context = []): self
    {
        return new self(
            "Required field missing: {$field}",
            400,
            null,
            array_merge(['field' => $field], $context)
        );
    }

    public static function invalidFormat(string $field, string $expectedFormat, array $context = []): self
    {
        return new self(
            "Invalid format for {$field}. Expected: {$expectedFormat}",
            400,
            null,
            array_merge(['field' => $field, 'expected_format' => $expectedFormat], $context)
        );
    }

    public static function valueTooSmall(string $field, mixed $minimum, array $context = []): self
    {
        return new self(
            "Value for {$field} must be at least {$minimum}",
            400,
            null,
            array_merge(['field' => $field, 'minimum' => $minimum], $context)
        );
    }

    public static function valueTooLarge(string $field, mixed $maximum, array $context = []): self
    {
        return new self(
            "Value for {$field} must not exceed {$maximum}",
            400,
            null,
            array_merge(['field' => $field, 'maximum' => $maximum], $context)
        );
    }
}
