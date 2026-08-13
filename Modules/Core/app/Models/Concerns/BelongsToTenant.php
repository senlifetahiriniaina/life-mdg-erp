<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Defense-in-depth tenant isolation at the model layer.
 *
 * On top of stancl/tenancy's database-per-tenant isolation, this trait adds a
 * global scope that constrains queries to the current tenant's `tenant_id` and
 * auto-stamps `tenant_id` on create. It is a no-op when there is no active
 * tenant context (central domain, console, superadmin), so it never breaks
 * seeders or cross-tenant admin tooling.
 *
 * Apply opt-in to models that carry a `tenant_id` column:
 *
 *     use Modules\Core\Models\Concerns\BelongsToTenant;
 *     class Invoice extends Model { use BelongsToTenant; }
 *
 * Use Model::withoutTenantScope() to bypass it explicitly.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = self::currentTenantKey();
            if ($tenantId !== null) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });

        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('tenant_id'))
                && self::currentTenantKey() !== null) {
                $model->setAttribute('tenant_id', self::currentTenantKey());
            }
        });
    }

    /** Active tenant key, or null when there is no tenant context. */
    protected static function currentTenantKey(): ?string
    {
        if (function_exists('tenancy') && tenancy()->tenant !== null) {
            return (string) tenancy()->tenant->getTenantKey();
        }

        return null;
    }

    /** Query the model without the tenant global scope (superadmin / seeders). */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope('tenant')->newQuery();
    }
}
