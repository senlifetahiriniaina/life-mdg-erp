<?php
declare(strict_types=1);
namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RfqPolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = null;
}
