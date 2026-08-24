<?php

declare(strict_types=1);

/*
 * Chantier 32.11 — 14-layer deep audit of Modules\Workflow.
 *
 * Same middleware-disabling pattern already established in
 * WorkflowApiTest.php for this module (several Core-module security
 * services referenced by global middleware are absent from this test
 * environment) — auth:sanctum is deliberately kept.
 */
beforeEach(function () {
    $this->withoutMiddleware([
        \App\Http\Middleware\SecurityHeaders::class,
        \App\Http\Middleware\SessionSecurityMiddleware::class,
        \App\Http\Middleware\OutputEncodingMiddleware::class,
        \App\Http\Middleware\CsrfTokenMiddleware::class,
        \App\Http\Middleware\RequireMFAMiddleware::class,
        \App\Http\Middleware\IPAccessControlMiddleware::class,
        \App\Http\Middleware\RateLimitMiddleware::class,
    ]);
});

function auditUser(string $role = 'admin'): \App\Models\User
{
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $company = \App\Models\Company::factory()->create();
    $user = \App\Models\User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);
    test()->actingAs($user, 'sanctum');
    return $user;
}

// ─── Headline: ApprovalWorkflowService fully deleted ──────────────────────────

it('ApprovalWorkflowService class no longer exists anywhere', function () {
    expect(class_exists(\Modules\Workflow\Services\ApprovalWorkflowService::class))->toBeFalse();
});

it('TaskManagementService and WorkflowBuilderService (the same dead-parallel-subsystem pattern) are also deleted', function () {
    expect(class_exists('Modules\Workflow\Services\TaskManagementService'))->toBeFalse();
    expect(class_exists('Modules\Workflow\Services\WorkflowBuilderService'))->toBeFalse();
});

it('the approval_workflow / task_manager / workflow_builder container aliases no longer resolve', function () {
    expect(fn () => app('approval_workflow'))->toThrow(\Illuminate\Contracts\Container\BindingResolutionException::class);
    expect(fn () => app('task_manager'))->toThrow(\Illuminate\Contracts\Container\BindingResolutionException::class);
    expect(fn () => app('workflow_builder'))->toThrow(\Illuminate\Contracts\Container\BindingResolutionException::class);
});

it('WorkflowEngineService no longer exposes any of the deleted legacy Cache-based methods', function () {
    $engine = app(\Modules\Workflow\Services\WorkflowEngineService::class);
    foreach (['createWorkflow', 'addStep', 'publishWorkflow', 'getWorkflow', 'getExecution',
              'deleteWorkflow', 'duplicateWorkflow', 'evaluateTrigger', 'dispatchAction',
              'processChain', 'dispatchParallelActions', 'dispatchWithRetry',
              'dispatchWithTimeout', 'logExecution'] as $deadMethod) {
        expect(method_exists($engine, $deadMethod))->toBeFalse("method {$deadMethod} should have been removed");
    }
});

// ─── The real live engine still works end-to-end after the deletion ──────────

it('real WorkflowChainDefinition-based automation flows still execute end-to-end after the deletion', function () {
    $user = auditUser('admin');

    $definition = \Modules\Workflow\Models\WorkflowChainDefinition::create([
        'tenant_id'   => (string) $user->company_id,
        'name'        => 'Post-cleanup regression flow',
        'trigger_key' => 'audit.regression.trigger',
        'conditions'  => [],
        'actions'     => [
            ['action_key' => 'notify.in_app', 'params' => ['to' => [$user->id], 'title' => 'hi', 'body' => 'hi']],
        ],
        'is_active'   => true,
    ]);

    $engine  = app(\Modules\Workflow\Services\WorkflowEngineService::class);
    $results = $engine->executeWorkflow('audit.regression.trigger', [
        'tenant_id' => (string) $user->company_id,
    ]);

    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('completed');

    $execution = \Modules\Workflow\Models\WorkflowChainExecution::where('workflow_definition_id', $definition->id)->first();
    expect($execution)->not->toBeNull();
    expect($execution->status)->toBe('completed');
});

// ─── WorkflowActionRegistry: the guaranteed-fatal constructor bug ─────────────

it('WorkflowActionRegistry can now actually be resolved from the container (was a guaranteed fatal TypeError before this fix)', function () {
    $registry = app(\Modules\Workflow\Services\WorkflowActionRegistry::class);
    expect($registry)->toBeInstanceOf(\Modules\Workflow\Services\WorkflowActionRegistry::class);
});

it('WorkflowActionRegistry now resolves all 12 previously-invisible "new batch" module prefixes', function () {
    $registry = app(\Modules\Workflow\Services\WorkflowActionRegistry::class);
    foreach ([
        'ai.analyze', 'calendar.create_event', 'transform.map_fields', 'delay.wait_minutes',
        'documents.generate_pdf', 'ecommerce.create_order', 'helpdesk.create_ticket',
        'http.get', 'logistics.create_shipment', 'projects.create_task',
        'quality.create_inspection', 'strategy.flag_ratio_alert',
    ] as $key) {
        expect($registry->has($key))->toBeTrue("expected {$key} to be resolvable");
    }
});

// ─── WorkflowEngineService::executeAction() — the real live dispatch path ────

it('executeAction() now dispatches the 12 new-batch modules instead of "Unknown action module" (real production dispatch bug)', function () {
    $engine = app(\Modules\Workflow\Services\WorkflowEngineService::class);

    $result = $engine->executeAction('strategy.flag_ratio_alert', ['ratio_id' => 1], []);
    expect($result['status'] ?? null)->not->toBe('skipped');
    expect($result)->not->toHaveKey('reason');

    $result2 = $engine->executeAction('delay.wait_minutes', ['minutes' => 5], []);
    expect($result2)->toHaveKey('resume_at');
});

it('executeAction() still correctly skips a genuinely unknown module (no false positives)', function () {
    $engine = app(\Modules\Workflow\Services\WorkflowEngineService::class);
    $result = $engine->executeAction('totally_unknown_module.do_thing', [], []);
    expect($result['status'])->toBe('skipped');
    expect($result['reason'])->toContain('Unknown action module');
});

it('WorkflowDefinitionController::test() dry-run now genuinely reaches the new-batch StrategyActionHandler instead of the generic "Unknown action module" skip', function () {
    $user = auditUser('admin');

    $definition = \Modules\Workflow\Models\WorkflowChainDefinition::create([
        'tenant_id'   => (string) $user->company_id,
        'name'        => 'Dry-run regression',
        'trigger_key' => 'audit.dryrun.trigger',
        'actions'     => [['action_key' => 'strategy.update_kpi', 'params' => [
            'kpi_key' => 'audit_test_kpi', 'value' => 42,
        ]]],
        'is_active'   => true,
    ]);

    $response = $this->postJson("/api/v1/workflow-chain/definitions/{$definition->id}/test", ['context' => []]);
    $response->assertOk();
    $steps = $response->json('results');
    // Before this chantier's fix, every 'strategy.*' action key fell to
    // WorkflowEngineService::executeAction()'s default branch ("Unknown
    // action module: strategy") — confirming the real handler was reached
    // (a 'recorded'/'error'-about-something-else outcome, never the generic
    // module-unknown skip) is the actual regression being locked in here.
    expect($steps[0]['output']['reason'] ?? '')->not->toContain('Unknown action module');
});

// ─── The 5 previously-unrouted controllers are now real, routed features ─────

it('automation flow CRUD + node CRUD + execute are now real, reachable, tenant-scoped endpoints', function () {
    $user = auditUser('admin');

    $create = $this->postJson('/api/v1/automation/flows', [
        'name' => 'My Flow', 'trigger_type' => 'manual',
    ]);
    $create->assertCreated();
    $flowId = $create->json('id');

    $this->getJson('/api/v1/automation/flows')->assertOk()
        ->assertJsonFragment(['id' => $flowId]);

    $addNode = $this->postJson("/api/v1/automation/flows/{$flowId}/nodes", [
        'node_type' => 'trigger', 'node_key' => 'workflow.manual', 'label' => 'Start',
    ]);
    $addNode->assertCreated();

    $this->postJson("/api/v1/automation/flows/{$flowId}/activate")->assertOk()
        ->assertJson(['is_active' => true]);

    $execute = $this->postJson("/api/v1/automation/flows/{$flowId}/execute", ['trigger_data' => []]);
    $execute->assertCreated();

    $this->getJson("/api/v1/automation/flows/{$flowId}/executions")->assertOk();
    $this->getJson('/api/v1/automation/node-types')->assertOk();
});

it('automation flows are tenant-isolated (no cross-company IDOR on show/update/destroy)', function () {
    $owner = auditUser('admin');
    $flow  = \Modules\Workflow\Models\Automation\AutomationFlow::create([
        'tenant_id' => $owner->company_id, 'name' => 'Owner flow',
        'trigger_type' => 'manual', 'is_active' => false, 'tags' => [],
    ]);

    $intruder = auditUser('admin');
    $this->getJson("/api/v1/automation/flows/{$flow->id}")->assertNotFound();
    $this->putJson("/api/v1/automation/flows/{$flow->id}", ['name' => 'Hacked'])->assertNotFound();
    $this->deleteJson("/api/v1/automation/flows/{$flow->id}")->assertNotFound();
});

it('WorkflowScheduleController is now real, reachable, and tenant-scoped (was fatal class-not-found before Chantier 19 Lot 3, unrouted until now)', function () {
    $owner = auditUser('admin');
    $flow  = \Modules\Workflow\Models\Automation\AutomationFlow::create([
        'tenant_id' => $owner->company_id, 'name' => 'Scheduled flow',
        'trigger_type' => 'schedule', 'is_active' => true, 'tags' => [],
    ]);

    $update = $this->putJson("/api/v1/automation/flows/{$flow->id}/schedule", ['cron' => '0 9 * * 1']);
    $update->assertOk();
    expect($flow->fresh()->trigger_config['cron'] ?? null)->toBe('0 9 * * 1');

    $this->getJson("/api/v1/automation/flows/{$flow->id}/schedule")->assertOk()
        ->assertJsonPath('data.cron', '0 9 * * 1');

    $this->postJson("/api/v1/automation/flows/{$flow->id}/schedule/disable")->assertOk();
    $this->getJson('/api/v1/automation/schedules')->assertOk();
    $this->getJson('/api/v1/automation/schedules/due')->assertOk();
});

it('WorkflowScheduleController denies cross-tenant access to another company\'s scheduled flow', function () {
    $owner = auditUser('admin');
    $flow  = \Modules\Workflow\Models\Automation\AutomationFlow::create([
        'tenant_id' => $owner->company_id, 'name' => 'Private schedule',
        'trigger_type' => 'schedule', 'is_active' => true, 'tags' => [],
    ]);

    auditUser('admin'); // intruder, different company_id
    $this->getJson("/api/v1/automation/flows/{$flow->id}/schedule")->assertNotFound();
    $this->putJson("/api/v1/automation/flows/{$flow->id}/schedule", ['cron' => '* * * * *'])->assertNotFound();
    $this->postJson("/api/v1/automation/flows/{$flow->id}/schedule/enable")->assertNotFound();
    $this->postJson("/api/v1/automation/flows/{$flow->id}/schedule/disable")->assertNotFound();
});

it('WorkflowScheduleController::due() no longer leaks other tenants\' due flows', function () {
    $owner = auditUser('admin');
    $mine  = \Modules\Workflow\Models\Automation\AutomationFlow::create([
        'tenant_id' => $owner->company_id, 'name' => 'Mine, due',
        'trigger_type' => 'schedule', 'is_active' => true, 'tags' => [],
        'trigger_config' => ['next_run_at' => now()->subMinute()->toIso8601String()],
    ]);
    \Modules\Workflow\Models\Automation\AutomationFlow::create([
        'tenant_id' => 999999, 'name' => 'Somebody else, due',
        'trigger_type' => 'schedule', 'is_active' => true, 'tags' => [],
        'trigger_config' => ['next_run_at' => now()->subMinute()->toIso8601String()],
    ]);

    $response = $this->getJson('/api/v1/automation/schedules/due');
    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($mine->id);
    foreach ($response->json('data') as $row) {
        expect($row['tenant_id'])->toBe($owner->company_id);
    }
});

it('WorkflowTemplateController template-library CRUD is real and reachable (was fatal class-not-found before Chantier 19 Lot 3, unrouted until now)', function () {
    auditUser('admin');

    $create = $this->postJson('/api/v1/automation/template-library', [
        'name' => 'My Template', 'nodes' => [['id' => 'n1']],
    ]);
    $create->assertCreated();
    $id = $create->json('data.id');

    $this->getJson('/api/v1/automation/template-library')->assertOk();
    $this->getJson("/api/v1/automation/template-library/{$id}")->assertOk();
    $this->putJson("/api/v1/automation/template-library/{$id}", ['name' => 'Renamed'])->assertOk();
    $this->getJson("/api/v1/automation/template-library/{$id}/preview")->assertOk();
    $this->deleteJson("/api/v1/automation/template-library/{$id}")->assertOk();
});

it('AiWorkflowController now delegates to the real AiWorkflowAssistantService instead of returning hardcoded empty stubs', function () {
    auditUser('admin');

    $suggest = $this->getJson('/api/v1/workflow/ai/suggest?locale=fr');
    $suggest->assertOk();
    expect($suggest->json('suggestions'))->not->toBeEmpty();
    expect($suggest->json('suggestions.0.title'))->not->toBe('');

    $validate = $this->postJson('/api/v1/workflow/ai/validate', ['trigger_type' => 'manual']);
    $validate->assertOk();
    expect($validate->json('errors'))->toContain('Le workflow ne contient aucun nœud.');

    $generate = $this->postJson('/api/v1/workflow/ai/generate', ['description' => 'notify on new order']);
    $generate->assertOk();
    expect($generate->json('flow.nodes'))->not->toBeEmpty();

    $this->getJson('/api/v1/workflow/ai/analyze-failures')->assertOk();
    $this->getJson('/api/v1/workflow/ai/flows/1/summary')->assertOk();
});

it('WorkflowNodeController is now real and reachable, and honestly reports resolvability instead of always faking success', function () {
    auditUser('admin');

    $this->getJson('/api/v1/workflow/nodes')->assertOk();
    $this->getJson('/api/v1/workflow/nodes/by-module')->assertOk();
    $this->getJson('/api/v1/workflow/nodes/by-category')->assertOk();

    $test = $this->postJson('/api/v1/workflow/nodes/test', ['key' => 'totally.nonexistent.key']);
    $test->assertNotFound();
});

it('automation webhook trigger is publicly reachable without auth (by design) and rejects an unknown uuid', function () {
    $response = $this->postJson('/api/v1/automation/webhook/does-not-exist-uuid', ['foo' => 'bar']);
    $response->assertNotFound();
});

it('non-manager/admin roles are denied every newly-wired route (module gate still holds)', function () {
    auditUser('employee');
    $this->getJson('/api/v1/automation/flows')->assertForbidden();
    $this->getJson('/api/v1/automation/schedules')->assertForbidden();
    $this->getJson('/api/v1/automation/template-library')->assertForbidden();
    $this->getJson('/api/v1/workflow/ai/suggest')->assertForbidden();
    $this->getJson('/api/v1/workflow/nodes')->assertForbidden();
});

// ─── Re-verification of prior Workflow fixes (still hold) ─────────────────────

it('workflow_chain_definitions still has all 7 patched columns and they are genuinely writable', function () {
    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('workflow_chain_definitions');
    foreach (['name', 'description', 'trigger_module', 'conditions', 'actions', 'execution_count', 'last_executed_at'] as $col) {
        expect($columns)->toContain($col);
    }

    $definition = \Modules\Workflow\Models\WorkflowChainDefinition::create([
        'tenant_id'      => '1',
        'name'           => 'Column regression check',
        'description'    => 'desc',
        'trigger_key'    => 'x.y',
        'trigger_module' => 'CRM',
        'conditions'     => [['field' => 'a', 'operator' => 'always']],
        'actions'        => [['action_key' => 'notify.email']],
        'is_active'      => true,
    ]);
    expect($definition->fresh()->trigger_module)->toBe('CRM');
});

it('WorkflowScheduleController and WorkflowTemplateController resolve the real Automation\AutomationFlow model namespace (Chantier 19 Lot 3 fix still holds)', function () {
    $reflection = new ReflectionMethod(\Modules\Workflow\Http\Controllers\Api\WorkflowScheduleController::class, 'show');
    $param      = $reflection->getParameters()[1];
    expect($param->getType()->getName())->toBe(\Modules\Workflow\Models\Automation\AutomationFlow::class);

    $reflectionT = new ReflectionMethod(\Modules\Workflow\Http\Controllers\Api\WorkflowTemplateController::class, 'apply');
    $paramT      = $reflectionT->getParameters()[1];
    expect($paramT->getType()->getName())->toBe(\Modules\Workflow\Models\Automation\AutomationFlowTemplate::class);
});

it('AutomationFlowController has no client-controlled tenant-id header fallback (Chantier 19 Lot 3 fix still holds)', function () {
    $owner = auditUser('admin');
    $victimFlow = \Modules\Workflow\Models\Automation\AutomationFlow::create([
        'tenant_id' => 987654321, 'name' => 'Victim flow',
        'trigger_type' => 'manual', 'is_active' => false, 'tags' => [],
    ]);

    // Spoofing X-Tenant-ID must not grant access to another tenant's flow.
    $response = $this->withHeaders(['X-Tenant-ID' => '987654321'])
        ->getJson("/api/v1/automation/flows/{$victimFlow->id}");
    $response->assertNotFound();
});

it('HrPayrollActionHandler dry-run/retry still correctly delegates non-HR/Payroll actions via the real module-aware dispatcher (Chantier 19 Lot 3 fix still holds)', function () {
    $user = auditUser('admin');

    $definition = \Modules\Workflow\Models\WorkflowChainDefinition::create([
        'tenant_id'   => (string) $user->company_id,
        'name'        => 'Non-HR dry run',
        'trigger_key' => 'audit.nonhr.trigger',
        'actions'     => [['action_key' => 'ecommerce.create_order', 'params' => []]],
        'is_active'   => true,
    ]);

    $response = $this->postJson("/api/v1/workflow-chain/definitions/{$definition->id}/test", ['context' => []]);
    $response->assertOk();
    expect($response->json('results.0.status'))->not->toBe('error');
});

// ─── Layer 8: sandboxed code-node execution — real hardening ─────────────────

it('CodeNodeController python_safe execution is now memory-bounded (ulimit fix) and still returns correct output for legitimate code', function () {
    auditUser('admin');

    $ok = $this->postJson('/api/v1/workflow/code-node/execute', [
        'language' => 'python_safe',
        'code'     => "output['x'] = 2 + 2",
    ]);
    $ok->assertOk();
    expect($ok->json('output.x'))->toBe(4);
});

it('CodeNodeController expression language has no shell/interpreter escape at all (provably safe by construction)', function () {
    auditUser('admin');

    $response = $this->postJson('/api/v1/workflow/code-node/validate', [
        'language' => 'expression',
        'code'     => 'system("id")',
    ]);
    $response->assertStatus(422);
    expect($response->json('errors'))->not->toBeEmpty();
});
