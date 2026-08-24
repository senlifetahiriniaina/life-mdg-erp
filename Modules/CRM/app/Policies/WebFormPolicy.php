<?php

declare(strict_types=1);

namespace Modules\CRM\Policies;

use App\Models\User;
use Modules\CRM\Models\WebForm;

/**
 * Chantier 32.15 (CRM 14-layer audit): WebFormController had zero authorize()/tenant-scoping
 * calls anywhere — any authenticated CRM-module user of any company could list/read/update/
 * delete every other company's public lead-capture forms (field definitions, submission
 * counts). crm_web_forms already carried a real `tenant_id` column, just never populated by
 * store() nor filtered on anywhere — both fixed alongside this new policy. The public
 * submit() endpoint is deliberately untouched by this policy (it is, and must remain,
 * unauthenticated) — see WebFormController's own fix for how the resulting Lead is now
 * correctly tenant-tagged from the form's own tenant_id instead.
 */
class WebFormPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WebForm $form): bool
    {
        return $this->sameCompany($user, $form);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WebForm $form): bool
    {
        return $this->sameCompany($user, $form);
    }

    public function delete(User $user, WebForm $form): bool
    {
        return $this->sameCompany($user, $form) && $user->hasAnyRole(['super-admin', 'admin', 'manager']);
    }

    private function sameCompany(User $user, WebForm $form): bool
    {
        return ((int) ($user->company_id ?? 0)) === ((int) ($form->tenant_id ?? 0));
    }
}
