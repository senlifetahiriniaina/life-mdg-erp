<?php
declare(strict_types=1);
namespace App\Policies;
class OpportunityPolicy extends BaseErpPolicy { protected ?string $ownerColumn = 'owner_id'; }
