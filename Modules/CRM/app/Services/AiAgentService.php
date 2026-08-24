<?php

declare(strict_types=1);

namespace Modules\CRM\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\AiAgent;
use Modules\CRM\Models\AiAgentRun;

class AiAgentService
{
    public function createAgent(array $data): AiAgent
    {
        return AiAgent::create($data);
    }

    /**
     * Chantier 38.3: defense-in-depth for the write path — AiAgentController::run() now
     * validates entity_type against the real contact/account/lead/opportunity allowlist and
     * checks entity_id ownership before ever reaching here, but this service method is also
     * reachable directly (e.g. the orphaned runEventAgents(), zero real callers today but a
     * future one could wire it up) — so the raw crm_activities insert below only ever writes
     * a real, allowlisted alias as subject_type, never an arbitrary caller-supplied string.
     * Closes the landmine at the actual write site, not just at today's one real caller.
     */
    private const ALLOWED_ENTITY_TYPES = ['contact', 'account', 'lead', 'opportunity'];

    public function runAgent(AiAgent $agent, string $entityType, int $entityId): AiAgentRun
    {
        $startTime = microtime(true);
        $safeSubjectType = in_array($entityType, self::ALLOWED_ENTITY_TYPES, true) ? $entityType : null;

        // Validate conditions (always pass in this stub)
        $run = new AiAgentRun([
            'agent_id' => $agent->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'status' => 'success',
            'executed_at' => now(),
        ]);

        // Execute action based on agent->action_type
        switch ($agent->action_type) {
            case 'add_note':
                DB::table('crm_activities')->insert([
                    'user_id' => $agent->created_by ?? 1,
                    // Chantier 32.15: crm_activities.company_id (added Chantier "CRM
                    // tenant-isolation follow-up") was never populated here — an agent-created
                    // note was silently invisible to ActivityController::index()'s
                    // (already-correct) company_id filter. Tagged from the owning agent's own
                    // tenant_id, the only tenant context available in this raw-insert path.
                    'company_id' => $agent->tenant_id,
                    'type' => 'note',
                    'title' => 'AI Agent Note',
                    'subject_type' => $safeSubjectType,
                    'subject_id' => $safeSubjectType !== null ? $entityId : null,
                    'status' => 'done',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                break;

            case 'create_task':
                DB::table('crm_activities')->insert([
                    'user_id' => $agent->created_by ?? 1,
                    'company_id' => $agent->tenant_id,
                    'type' => 'task',
                    'title' => 'AI Agent Task',
                    'subject_type' => $safeSubjectType,
                    'subject_id' => $safeSubjectType !== null ? $entityId : null,
                    'status' => 'planned',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                break;

            case 'update_field':
            case 'score_lead':
            case 'send_email':
            case 'assign_owner':
                // stub — no action
                break;
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        $run->result = ['action' => $agent->action_type, 'entity_id' => $entityId];
        $run->duration_ms = $durationMs;
        $run->save();

        $agent->incrementRunCount();

        return $run;
    }

    public function runScheduledAgents(?int $companyId = null): array
    {
        $agents = AiAgent::where('trigger_type', 'schedule')
            ->where('is_active', true)
            ->where('tenant_id', $companyId)
            ->get();

        $runs = [];

        foreach ($agents as $agent) {
            $runs[] = $this->runAgent($agent, 'System', 0);
        }

        return $runs;
    }

    public function runEventAgents(string $eventType, string $entityType, int $entityId): array
    {
        $agents = AiAgent::where('trigger_type', 'event')
            ->where('is_active', true)
            ->get()
            ->filter(function (AiAgent $agent) use ($eventType) {
                $config = $agent->trigger_config ?? [];

                return isset($config['event']) && $config['event'] === $eventType;
            });

        $runs = [];

        foreach ($agents as $agent) {
            $runs[] = $this->runAgent($agent, $entityType, $entityId);
        }

        return $runs;
    }

    public function getAgentHistory(AiAgent $agent, int $limit = 50): Collection
    {
        return $agent->runs()
            ->latest('executed_at')
            ->limit($limit)
            ->get();
    }

    public function getAgentStats(AiAgent $agent): array
    {
        $runs = $agent->runs();

        $totalRuns = $runs->count();
        $successRuns = (clone $runs)->where('status', 'success')->count();

        $successRate = $totalRuns > 0
            ? round(($successRuns / $totalRuns) * 100, 2)
            : 0.0;

        $avgDurationMs = (clone $runs)->whereNotNull('duration_ms')->avg('duration_ms');

        return [
            'total_runs' => $totalRuns,
            'success_rate' => $successRate,
            'last_run_at' => $agent->last_run_at,
            'avg_duration_ms' => $avgDurationMs ? (float) round((float) $avgDurationMs, 2) : null,
        ];
    }

    public function toggleAgent(AiAgent $agent): AiAgent
    {
        $agent->update(['is_active' => ! $agent->is_active]);

        return $agent->fresh();
    }

    public function deleteAgent(AiAgent $agent): void
    {
        $agent->runs()->delete();
        $agent->delete();
    }
}
