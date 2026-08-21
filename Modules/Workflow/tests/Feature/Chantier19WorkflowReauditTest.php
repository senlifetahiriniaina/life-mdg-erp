<?php

declare(strict_types=1);

/*
 * Chantier 19 Lot 3 — Workflow re-verification (empirical execution). See
 * CLAUDE.md's own "Chantier 19 Lot 3" entry for the full write-up. Covers:
 *
 *  1. WorkflowExecutionController::tenantId() — was the phantom
 *     `users.tenant_id ?? users.id` fallback, meaning `workflow-chain/
 *     executions*` (WorkflowExecutionController) silently never matched
 *     what the sibling, correctly-scoped `workflow/executions*`
 *     (WorkflowChainController) route actually writes.
 *  2. CrmSalesActionHandler::notifySalesTeam() — claimed `notified: true`
 *     on every call while never actually writing a row (real
 *     `notifications` table has a UUID primary key + no `tenant_id`
 *     column, silently swallowed by its own try/catch).
 *  3. FlowVersionController/FlowVersionService — zero per-record ownership
 *     check on a live, routed `flows/{id}/versions*` endpoint.
 *  4. WorkflowScheduleController / WorkflowTemplateController — fatal
 *     class-not-found bug (`Modules\Workflow\Models\AutomationFlow`
 *     doesn't exist; the real class is one namespace level deeper).
 *  5. AI-assist controllers' phantom `users.role` → real Spatie role fix.
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Workflow\Models\Automation\AutomationFlow;
use Modules\Workflow\Models\WorkflowChainDefinition;
use Modules\Workflow\Models\WorkflowChainExecution;
use Modules\Workflow\Services\FlowVersionService;
use Spatie\Permission\Models\Role;

function workflowReauditUser(string $role = 'admin'): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $company = Company::factory()->create();
    $user    = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

it('makes a real chain trigger visible through the previously-mismatched WorkflowExecutionController route', function () {
    $user = workflowReauditUser('admin');

    $definition = WorkflowChainDefinition::create([
        'tenant_id'      => $user->company_id,
        'name'           => 'Notify sales on won opportunity',
        'trigger_key'    => 'crm.opportunity.won',
        'trigger_module' => 'CRM',
        'actions'        => [
            ['action_key' => 'sales.notify_sales_team', 'params' => ['message' => 'Test']],
        ],
        'is_active' => true,
    ]);

    // Trigger it for real, synchronously, over HTTP.
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/workflow/trigger', [
            'trigger_key' => 'crm.opportunity.won',
            'context'     => ['opportunity_id' => 42],
            'sync'        => true,
        ])->assertOk();

    $execution = WorkflowChainExecution::where('workflow_definition_id', $definition->id)->first();
    expect($execution)->not->toBeNull();
    expect((int) $execution->tenant_id)->toBe((int) $user->company_id);

    // Before the fix, WorkflowExecutionController::tenantId() read
    // `users.tenant_id ?? users.id` (always falling to the caller's own
    // users.id, which never matches the real company_id-derived tenant_id
    // written above) — this real execution would never have appeared here.
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/workflow-chain/executions')
        ->assertOk()
        ->assertJsonPath('data.0.id', $execution->id);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/workflow-chain/executions/{$execution->id}")
        ->assertOk()
        ->assertJsonPath('id', $execution->id);

    // And the notify_sales_team action genuinely wrote a notification row —
    // not just claimed success while silently swallowing a real SQL error.
    expect(DB::table('notifications')->where('type', 'workflow.sales_team_alert')->count())->toBe(1);
});

it('rejects a retry from a different company via the fixed WorkflowExecutionController::retry()', function () {
    $userA = workflowReauditUser('admin');
    $userB = workflowReauditUser('admin');

    $definition = WorkflowChainDefinition::create([
        'tenant_id'      => $userA->company_id,
        'name'           => 'A-only workflow',
        'trigger_key'    => 'crm.opportunity.won',
        'trigger_module' => 'CRM',
        'actions'        => [['action_key' => 'sales.notify_sales_team', 'params' => []]],
        'is_active'      => true,
    ]);

    $execution = WorkflowChainExecution::create([
        'workflow_definition_id' => $definition->id,
        'tenant_id'              => $userA->company_id,
        'trigger_key'            => $definition->trigger_key,
        'context_snapshot'       => [],
        'status'                 => 'failed',
        'started_at'             => now(),
        'completed_at'           => now(),
    ]);

    $this->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/workflow-chain/executions/{$execution->id}/retry")
        ->assertForbidden();

    $this->actingAs($userA, 'sanctum')
        ->postJson("/api/v1/workflow-chain/executions/{$execution->id}/retry")
        ->assertCreated();
});

it('closes the FlowVersionController cross-tenant IDOR on a live, routed endpoint', function () {
    $userA = workflowReauditUser('manager');
    $userB = workflowReauditUser('manager');

    $flowA = AutomationFlow::create([
        'tenant_id'  => $userA->company_id,
        'name'       => 'Flow A',
        'created_by' => $userA->id,
    ]);

    $version = app(FlowVersionService::class)->createVersion($flowA->id, 'v1', $userA->id, $userA->company_id);

    // Before the fix, any manager/admin of ANY company could list/create/
    // restore version snapshots for ANY AutomationFlow by id — confirmed
    // empirically via FlowVersionService::findFlowOrFail() never filtering
    // by tenant at all.
    $this->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/flows/{$flowA->id}/versions")
        ->assertNotFound();

    $this->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/flows/{$flowA->id}/versions", ['label' => 'hostile snapshot'])
        ->assertNotFound();

    $this->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/flows/{$flowA->id}/versions/{$version->id}/restore")
        ->assertUnprocessable();

    // The real owner can still use it normally.
    $this->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/flows/{$flowA->id}/versions")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('fixes the fatal class-not-found bug in WorkflowScheduleController and WorkflowTemplateController', function () {
    // Chantier 32.11: both controllers are now genuinely routed (see
    // routes/api.php) — rewritten from direct, unauthenticated PHP method
    // calls (which this chantier's own new assertOwnership() check now
    // correctly rejects, since request()->user() is null without
    // actingAs()) to real HTTP requests through the real route, matching
    // this module's other real Feature tests.
    $user = workflowReauditUser('admin');
    $this->actingAs($user, 'sanctum');

    $flow = AutomationFlow::create([
        'tenant_id'  => $user->company_id,
        'name'       => 'Scheduled flow',
        'created_by' => $user->id,
    ]);

    // Before the Chantier 19 Lot 3 fix: `Class
    // "Modules\Workflow\Models\AutomationFlow" not found` on every call —
    // both classes' imports pointed at a namespace one level too shallow.
    $this->getJson("/api/v1/automation/flows/{$flow->id}/schedule")
        ->assertOk()
        ->assertJsonPath('data.flow_id', $flow->id);

    $this->getJson('/api/v1/automation/template-library')
        ->assertOk();
});

it('runs the Workflow AI-assist endpoint without a fatal error using the real Spatie role', function () {
    $user = workflowReauditUser('manager');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/workflow/ai/assist', [
            'action' => 'view_dashboard',
            'locale' => 'fr',
        ])
        ->assertOk()
        ->assertJsonStructure(['enabled', 'what_to_do', 'how_to_do']);
});
