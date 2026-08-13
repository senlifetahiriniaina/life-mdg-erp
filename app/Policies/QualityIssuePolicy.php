<?php

declare(strict_types=1);

namespace App\Policies;

use Modules\Quality\Models\QualityIssue;

class QualityIssuePolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = 'reported_by';
}
