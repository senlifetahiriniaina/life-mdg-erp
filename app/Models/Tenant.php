<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Stancl\Tenancy\Database\Models\Tenant as StanclTenant;

class Tenant extends StanclTenant
{
    use HasFactory;

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }
}
