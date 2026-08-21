<?php

declare(strict_types=1);

namespace Modules\CRM\Policies;

use App\Models\User;
use Modules\CRM\Models\Quote;

/**
 * Chantier 32.15 (CRM 14-layer audit): QuoteController had zero authorize()/tenant-scoping
 * calls anywhere — any authenticated CRM-module user of any company could list/read/update/
 * delete/duplicate/export-PDF every other company's CPQ quotes (pricing, discounts, contact
 * linkage), confirmed empirically before this fix. crm_quotes already carried a real
 * `tenant_id` column, just never populated by CpqService::createQuote()/duplicate() nor
 * filtered on anywhere — both fixed alongside this new policy.
 */
class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Quote $quote): bool
    {
        return $this->sameCompany($user, $quote);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Quote $quote): bool
    {
        return $this->sameCompany($user, $quote);
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $this->sameCompany($user, $quote) && $user->hasAnyRole(['super-admin', 'admin', 'manager']);
    }

    private function sameCompany(User $user, Quote $quote): bool
    {
        return ((int) ($user->company_id ?? 0)) === ((int) ($quote->tenant_id ?? 0));
    }
}
