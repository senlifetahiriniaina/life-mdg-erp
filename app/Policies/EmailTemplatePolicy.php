<?php

declare(strict_types=1);

namespace App\Policies;

class EmailTemplatePolicy extends BaseErpPolicy
{
    /** Creator owns the template. */
    protected ?string $ownerColumn = 'created_by';
}
