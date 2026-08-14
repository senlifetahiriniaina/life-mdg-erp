<?php

declare(strict_types=1);

namespace Modules\Setup\Exceptions;

use RuntimeException;

class ModuleDeactivationBlockedException extends RuntimeException
{
    /** @param array<int, string> $dependents */
    public function __construct(private readonly string $module, private readonly array $dependents)
    {
        parent::__construct(sprintf(
            "Cannot deactivate '%s': still required by %s.",
            $module,
            implode(', ', $dependents)
        ));
    }

    public function getModule(): string
    {
        return $this->module;
    }

    /** @return array<int, string> */
    public function getDependents(): array
    {
        return $this->dependents;
    }
}
