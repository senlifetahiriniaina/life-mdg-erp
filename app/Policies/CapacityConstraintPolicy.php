<?php

declare(strict_types=1);

namespace App\Policies;

class CapacityConstraintPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = null;
}
