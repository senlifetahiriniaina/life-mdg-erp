<?php
declare(strict_types=1);
namespace App\Policies;
class FolderPolicy extends BaseErpPolicy { protected ?string $ownerColumn = 'created_by'; }
