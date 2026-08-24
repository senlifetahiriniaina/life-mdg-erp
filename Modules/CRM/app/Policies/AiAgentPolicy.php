<?php

declare(strict_types=1);

namespace Modules\CRM\Policies;

use App\Models\User;
use Modules\CRM\Models\AiAgent;

/**
 * Chantier 32.15 (CRM 14-layer audit): AiAgentController had zero authorize()/tenant-scoping
 * calls anywhere — any authenticated CRM-module user of any company could list/read/update/
 * delete every other company's automation agents, and — more severely — run() any other
 * company's agent against an arbitrary entity_type/entity_id, a real cross-tenant write
 * vector (action_type update_field/assign_owner/score_lead mutate the referenced record).
 * Zero real Vue caller was found for this subsystem (a "real API, no UI yet" gap, not fake/
 * dead — real persistence, real service, real write actions), so it is fixed rather than
 * deleted, matching this session's "activate, don't delete real business logic" precedent.
 * crm_ai_agents/crm_ai_agent_runs already carried a real `tenant_id` column, just never
 * populated/filtered — fixed alongside this new policy.
 */
class AiAgentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AiAgent $agent): bool
    {
        return $this->sameCompany($user, $agent);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AiAgent $agent): bool
    {
        return $this->sameCompany($user, $agent);
    }

    public function delete(User $user, AiAgent $agent): bool
    {
        return $this->sameCompany($user, $agent) && $user->hasAnyRole(['super-admin', 'admin', 'manager']);
    }

    public function run(User $user, AiAgent $agent): bool
    {
        return $this->sameCompany($user, $agent);
    }

    private function sameCompany(User $user, AiAgent $agent): bool
    {
        return ((int) ($user->company_id ?? 0)) === ((int) ($agent->tenant_id ?? 0));
    }
}
