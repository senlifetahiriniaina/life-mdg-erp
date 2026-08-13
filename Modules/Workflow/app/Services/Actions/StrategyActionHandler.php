<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * StrategyActionHandler — Phase 39
 *
 * Handles workflow actions for the Strategy module:
 * KPI updates, ratio alerts, CEO notifications, action plans.
 */
class StrategyActionHandler
{
    /**
     * Dispatch an action by its dot-notation suffix.
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function dispatch(string $action, array $params, array $context): array
    {
        return match ($action) {
            'strategy.flag_ratio_alert'       => $this->flagRatioAlert($params, $context),
            'strategy.update_kpi'             => $this->updateKpi($params, $context),
            'strategy.notify_ceo'             => $this->notifyCeo($params, $context),
            'strategy.create_action_plan'     => $this->createActionPlan($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown Strategy action: {$action}"],
        };
    }

    /**
     * action: strategy.flag_ratio_alert
     * Create a strategic alert when a KPI ratio deviates from its target/benchmark.
     *
     * @param  array<string,mixed>  $params   e.g. ['ratio_key' => 'crm.win_rate', 'severity' => 'warning']
     * @param  array<string,mixed>  $context
     * @return array{alert_id: int|null, status: string}
     */
    public function flagRatioAlert(array $params, array $context): array
    {
        $tenantId   = $context['tenant_id'] ?? 1;
        $ratioKey   = $params['ratio_key'] ?? ($context['ratio_key'] ?? 'unknown');
        $severity   = $params['severity'] ?? 'warning'; // ok | warning | critical
        $value      = $context['ratio_value'] ?? $params['value'] ?? null;
        $benchmark  = $params['benchmark'] ?? null;
        $message    = $params['message'] ?? "Alerte ratio: {$ratioKey} a dévié de la cible";

        try {
            $alertId = DB::table('strategy_alerts')->insertGetId([
                'tenant_id'   => $tenantId,
                'ratio_key'   => $ratioKey,
                'severity'    => $severity,
                'value'       => $value,
                'benchmark'   => $benchmark,
                'message'     => $message,
                'status'      => 'open',
                'source'      => 'workflow_automation',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            Log::info('WorkflowAction: strategy ratio alert flagged', [
                'alert_id'  => $alertId,
                'ratio_key' => $ratioKey,
                'severity'  => $severity,
            ]);

            return ['alert_id' => $alertId, 'status' => 'created', 'ratio_key' => $ratioKey, 'severity' => $severity];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: flagRatioAlert skipped', ['error' => $e->getMessage()]);
            return ['alert_id' => null, 'status' => 'simulated', 'ratio_key' => $ratioKey];
        }
    }

    /**
     * action: strategy.update_kpi
     * Push a new KPI value from a module event into the strategy KPI registry.
     *
     * @param  array<string,mixed>  $params   e.g. ['kpi_key' => 'hr.turnover_rate', 'value' => 12.5]
     * @param  array<string,mixed>  $context
     * @return array{snapshot_id: int|null, status: string}
     */
    public function updateKpi(array $params, array $context): array
    {
        $tenantId = $context['tenant_id'] ?? 1;
        $kpiKey   = $params['kpi_key'] ?? ($context['kpi_key'] ?? null);
        $value    = $params['value'] ?? ($context['kpi_value'] ?? null);
        $period   = $params['period'] ?? now()->format('Y-m');

        if (! $kpiKey || $value === null) {
            return ['status' => 'error', 'reason' => 'Missing kpi_key or value'];
        }

        try {
            $snapshotId = DB::table('strategy_ratio_snapshots')->insertGetId([
                'tenant_id'   => $tenantId,
                'ratio_key'   => $kpiKey,
                'value'       => (float) $value,
                'period'      => $period,
                'source'      => 'workflow_automation',
                'recorded_at' => now(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            return ['snapshot_id' => $snapshotId, 'status' => 'recorded', 'kpi_key' => $kpiKey, 'value' => $value];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: updateKpi skipped', ['error' => $e->getMessage()]);
            return ['snapshot_id' => null, 'status' => 'simulated', 'kpi_key' => $kpiKey];
        }
    }

    /**
     * action: strategy.notify_ceo
     * Send a strategic summary notification to users with the CEO role.
     *
     * @param  array<string,mixed>  $params   e.g. ['summary' => '...', 'urgency' => 'high']
     * @param  array<string,mixed>  $context
     * @return array{notified: bool, role: string}
     */
    public function notifyCeo(array $params, array $context): array
    {
        $tenantId = $context['tenant_id'] ?? 1;
        $summary  = $params['summary'] ?? ($context['ai_summary'] ?? 'Résumé stratégique automatique');
        $urgency  = $params['urgency'] ?? 'normal';

        try {
            // Fetch CEO user IDs for the tenant
            $ceoUsers = DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('roles.name', 'ceo')
                ->where('users.tenant_id', $tenantId)
                ->pluck('users.id');

            $notified = 0;
            foreach ($ceoUsers as $userId) {
                DB::table('notifications')->insert([
                    'tenant_id'       => $tenantId,
                    'notifiable_type' => 'user',
                    'notifiable_id'   => $userId,
                    'type'            => 'strategy.ceo_summary',
                    'data'            => json_encode([
                        'summary' => $summary,
                        'urgency' => $urgency,
                        'context' => array_intersect_key($context, array_flip(['ratio_key', 'period', 'alert_id'])),
                    ]),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
                $notified++;
            }
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: notifyCeo skipped', ['error' => $e->getMessage()]);
            return ['notified' => false, 'role' => 'ceo', 'status' => 'simulated'];
        }

        return ['notified' => true, 'role' => 'ceo', 'recipients' => $notified ?? 0];
    }

    /**
     * action: strategy.create_action_plan
     * Auto-create a strategic action plan when an alert triggers.
     *
     * @param  array<string,mixed>  $params   e.g. ['title' => 'Improve Win Rate Q3', 'owner_role' => 'sales_director']
     * @param  array<string,mixed>  $context
     * @return array{plan_id: int|null, status: string}
     */
    public function createActionPlan(array $params, array $context): array
    {
        $tenantId  = $context['tenant_id'] ?? 1;
        $alertId   = $context['alert_id'] ?? null;
        $ratioKey  = $context['ratio_key'] ?? $params['ratio_key'] ?? 'unknown';
        $title     = $params['title'] ?? "Plan d'action automatique: {$ratioKey}";
        $ownerRole = $params['owner_role'] ?? 'manager';
        $dueDate   = $params['due_date'] ?? now()->addDays(30)->toDateString();

        try {
            $planId = DB::table('strategy_action_plans')->insertGetId([
                'tenant_id'  => $tenantId,
                'alert_id'   => $alertId,
                'ratio_key'  => $ratioKey,
                'title'      => $title,
                'owner_role' => $ownerRole,
                'due_date'   => $dueDate,
                'status'     => 'draft',
                'source'     => 'workflow_automation',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('WorkflowAction: strategy action plan created', [
                'plan_id'   => $planId,
                'ratio_key' => $ratioKey,
            ]);

            return ['plan_id' => $planId, 'status' => 'created', 'ratio_key' => $ratioKey];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: createActionPlan skipped', ['error' => $e->getMessage()]);
            return ['plan_id' => null, 'status' => 'simulated', 'ratio_key' => $ratioKey];
        }
    }
}
