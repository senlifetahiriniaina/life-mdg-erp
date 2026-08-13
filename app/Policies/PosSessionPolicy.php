<?php

declare(strict_types=1);

namespace App\Policies;

class PosSessionPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = 'cashier_id';
}
