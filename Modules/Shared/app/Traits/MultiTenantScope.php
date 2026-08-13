<?php

declare(strict_types=1);

namespace Modules\Shared\Traits;

use Illuminate\Database\Eloquent\Builder;

trait MultiTenantScope
{
    /**
     * Boot the MultiTenantScope trait
     */
    public static function bootMultiTenantScope(): void
    {
        static::addGlobalScope('company_id', function (Builder $builder) {
            $companyId = auth()?->user()?->company_id ?? request()?->header('X-Company-ID');

            if ($companyId) {
                $builder->where('company_id', $companyId);
            }
        });

        static::creating(function ($model) {
            if (!$model->company_id && auth()?->user()?->company_id) {
                $model->company_id = auth()->user()->company_id;
            }
        });
    }

    /**
     * Scope to a specific company
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Get all records without company isolation (admin only)
     */
    public function scopeWithoutCompanyScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('company_id');
    }
}
