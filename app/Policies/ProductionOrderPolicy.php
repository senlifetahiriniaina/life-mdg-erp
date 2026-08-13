<?php
declare(strict_types=1);
namespace App\Policies;
class ProductionOrderPolicy extends BaseErpPolicy { protected ?string $ownerColumn = 'created_by'; }
