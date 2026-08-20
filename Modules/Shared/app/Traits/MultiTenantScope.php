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
            // Chantier 19 Lot 3: this resolved the tenant boundary as
            // `auth()?->user()?->company_id ?? request()?->header('X-Company-ID')`
            // — a client-controlled-header IDOR, the exact bug class already
            // fixed for Setup (Chantier 8.5sv) and for
            // Modules\Settings\Models\Setting::boot() (this same pass): any
            // authenticated user whose own company_id is null could set
            // `X-Company-ID: <victim>` to have every model using this trait
            // scoped to another company's data instead of their own. This
            // trait has zero real consumers anywhere in the app today (only
            // reflection-based tests reference it), but it is exported
            // Modules\Shared infrastructure any future model could adopt —
            // fixed here rather than left as a landmine for whoever uses it
            // next, matching this session's established precedent for
            // orphaned-but-exported shared code.
            // Second, independent bug fixed in the same pass: when no real
            // tenant resolves at all, the `if ($companyId)` guard used to
            // skip adding any filter whatsoever — returning every
            // company's rows completely unfiltered to such a caller, worse
            // than the header override since it needed no attacker action
            // at all (confirmed empirically). Fixed to fall back to
            // company_id IS NULL (a safe, conservative default: a caller
            // with no resolvable tenant sees only untenanted rows, never
            // another company's), matching Modules\Settings\Models\
            // Setting::boot()'s identical fix in the same pass.
            $companyId = auth()?->user()?->company_id;

            if ($companyId) {
                $builder->where('company_id', $companyId);
            } else {
                $builder->whereNull('company_id');
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
