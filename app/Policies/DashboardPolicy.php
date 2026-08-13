<?php
declare(strict_types=1);
namespace App\Policies;
class DashboardPolicy extends BaseErpPolicy { protected ?string $ownerColumn = 'user_id'; }
