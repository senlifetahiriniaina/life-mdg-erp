<?php

declare(strict_types=1);

namespace App\Policies;

class EmailSegmentPolicy extends BaseErpPolicy
{
    /** Shared resource — all agents see and manage all segments. */
    protected ?string $ownerColumn = null;
}
