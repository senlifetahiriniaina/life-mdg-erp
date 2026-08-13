<?php
declare(strict_types=1);
namespace App\Policies;
class OrderPolicy extends BaseErpPolicy { protected ?string $ownerColumn = 'user_id'; }
