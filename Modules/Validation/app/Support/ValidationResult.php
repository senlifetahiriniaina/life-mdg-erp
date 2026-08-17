<?php

declare(strict_types=1);

namespace Modules\Validation\Support;

class ValidationResult
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        private readonly bool $passes,
        private readonly array $errors = []
    ) {
    }

    public function passes(): bool
    {
        return $this->passes;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
