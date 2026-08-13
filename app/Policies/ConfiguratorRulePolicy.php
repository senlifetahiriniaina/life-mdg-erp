<?php

declare(strict_types=1);

namespace App\Policies;

class ConfiguratorRulePolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = null; // Shared resource
}
