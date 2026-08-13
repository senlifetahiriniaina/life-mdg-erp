<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

use Modules\Workflow\Models\WorkflowAction;
use Modules\Workflow\Models\WorkflowDefinition;
use Modules\Workflow\Models\WorkflowExecution;
use Modules\Workflow\Models\WorkflowExecutionLog;

/*
 * Several Core module services referenced by global middleware are absent from
 * apps/api/Modules/Core in this test environment (SecurityHeadersService,
 * NonceManager, SessionSecurityService, etc.). Disable them so HTTP routes
 * can be hit without booting the full security-middleware stack.
 * auth:sanctum is intentionally kept to verify authentication behaviour.
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

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Create and authenticate a test user without triggering Employee factory
 * (the global actingAsUser() creates hr_employees which may not exist in SQLite).
 */
function workflowUser(string $role = 'admin'): \App\Models\User
{
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $user = \App\Models\User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user, 'sanctum');
    return $user;
}

function workflowPayload(array $overrides = []): array
{
    return array_merge([
        'name'          => 'Test Workflow',
        'module'        => 'CRM',
        'trigger_event' => 'contact.created',
        'description'   => 'Sends a welcome email when a contact is created.',
        'is_active'     => true,
    ], $overrides);
}

function makeWorkflowDefinition(int $tenantId, array $overrides = []): WorkflowDefinition
{
    return WorkflowDefinition::create(array_merge([
        'tenant_id'     => $tenantId,
        'name'          => 'Fixture Workflow',
        'module'        => 'CRM',
        'trigger_event' => 'contact.created',
        'is_active'     => true,
        'created_by'    => 1,
    ], $overrides));
}

function makeWorkflowForUser(int $userId, array $overrides = []): WorkflowDefinition
{
    return makeWorkflowDefinition($userId, $overrides);
}

function makeAction(int $workflowId, array $overrides = []): WorkflowAction
{
    return WorkflowAction::create(array_merge([
        'workflow_id'   => $workflowId,
        'order'         => 0,
        'action_type'   => 'send_email',
        'action_config' => ['to' => 'user@example.com'],
    ], $overrides));
}

// ─── Authentication ────────────────────────────────────────────────────────────

test('unauthenticated request to list workflows returns 401', function () {
    $response = $this->getJson('/api/v1/workflows');
    $response->assertStatus(401);
});

test('unauthenticated request to create workflow returns 401', function () {
    $response = $this->postJson('/api/v1/workflows', workflowPayload());
    $response->assertStatus(401);
});

// ─── List Workflows ────────────────────────────────────────────────────────────

test('authenticated user can list their workflow definitions', function () {
    $user = workflowUser('admin');
    makeWorkflowForUser($user->id);
    makeWorkflowForUser($user->id, ['name' => 'Second Workflow']);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/workflows');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'module', 'trigger_event', 'is_active', 'actions'],
            ],
        ])
        ->assertJsonCount(2, 'data');
});

test('list workflows filters by module', function () {
    $user = workflowUser('admin');
    makeWorkflowForUser($user->id, ['module' => 'CRM']);
    makeWorkflowForUser($user->id, ['module' => 'HR']);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/workflows?module=CRM');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('list workflows filters by is_active', function () {
    $user = workflowUser('admin');
    makeWorkflowForUser($user->id, ['is_active' => true]);
    makeWorkflowForUser($user->id, ['is_active' => false]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/workflows?is_active=1');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

// ─── Create Workflow ───────────────────────────────────────────────────────────

test('can create a workflow definition', function () {
    $user = workflowUser('admin');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/workflows', workflowPayload());

    $response->assertStatus(201)
        ->assertJsonPath('name', 'Test Workflow')
        ->assertJsonPath('module', 'CRM')
        ->assertJsonPath('trigger_event', 'contact.created');

    $this->assertDatabaseHas('wfd_definitions', [
        'name'      => 'Test Workflow',
        'module'    => 'CRM',
        'tenant_id' => $user->id,
    ]);
});

test('can create a workflow with actions', function () {
    $user = workflowUser('admin');

    $payload = workflowPayload([
        'actions' => [
            ['action_type' => 'send_email', 'order' => 0, 'action_config' => ['to' => 'a@b.com']],
            ['action_type' => 'assign_user', 'order' => 1, 'action_config' => ['user_id' => 5]],
        ],
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/workflows', $payload);

    $response->assertStatus(201)
        ->assertJsonCount(2, 'actions');

    $this->assertDatabaseCount('wfd_actions', 2);
});

test('create workflow returns 422 when name is missing', function () {
    $user = workflowUser('admin');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/workflows', [
            'module'        => 'CRM',
            'trigger_event' => 'contact.created',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('create workflow returns 422 when trigger_event is missing', function () {
    $user = workflowUser('admin');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/workflows', [
            'name'   => 'Bad Workflow',
            'module' => 'CRM',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['trigger_event']);
});

test('create workflow returns 422 for invalid action_type', function () {
    $user = workflowUser('admin');

    $payload = workflowPayload([
        'actions' => [
            ['action_type' => 'invalid_type', 'order' => 0],
        ],
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/workflows', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['actions.0.action_type']);
});

// ─── Show Workflow ─────────────────────────────────────────────────────────────

test('can show a workflow definition with actions', function () {
    $user     = workflowUser('admin');
    $workflow = makeWorkflowForUser($user->id);
    makeAction($workflow->id);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/workflows/{$workflow->id}");

    $response->assertStatus(200)
        ->assertJsonPath('id', $workflow->id)
        ->assertJsonCount(1, 'actions');
});

test('show returns 404 for unknown workflow', function () {
    $user = workflowUser('admin');

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/workflows/99999');

    $response->assertStatus(404);
});

// ─── Update Workflow ───────────────────────────────────────────────────────────

test('can update a workflow definition', function () {
    $user     = workflowUser('admin');
    $workflow = makeWorkflowForUser($user->id);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/workflows/{$workflow->id}", [
            'name'          => 'Updated Name',
            'trigger_event' => 'contact.updated',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('name', 'Updated Name')
        ->assertJsonPath('trigger_event', 'contact.updated');

    $this->assertDatabaseHas('wfd_definitions', [
        'id'            => $workflow->id,
        'name'          => 'Updated Name',
        'trigger_event' => 'contact.updated',
    ]);
});

test('update replaces actions when actions array is provided', function () {
    $user     = workflowUser('admin');
    $workflow = makeWorkflowForUser($user->id);
    makeAction($workflow->id, ['action_type' => 'send_email']);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/workflows/{$workflow->id}", [
            'actions' => [
                ['action_type' => 'webhook', 'order' => 0, 'action_config' => ['url' => 'https://example.com']],
            ],
        ]);

    $response->assertStatus(200)
        ->assertJsonCount(1, 'actions')
        ->assertJsonPath('actions.0.action_type', 'webhook');
});

// ─── Delete Workflow ───────────────────────────────────────────────────────────

test('can delete (deactivate) a workflow definition', function () {
    $user     = workflowUser('admin');
    $workflow = makeWorkflowForUser($user->id);

    $response = $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/workflows/{$workflow->id}");

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Workflow deleted.');

    $this->assertSoftDeleted('wfd_definitions', ['id' => $workflow->id]);
});

// ─── Toggle Active/Inactive ────────────────────────────────────────────────────

test('can toggle workflow to inactive', function () {
    $user     = workflowUser('admin');
    $workflow = makeWorkflowForUser($user->id, ['is_active' => true]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/workflows/{$workflow->id}/toggle");

    $response->assertStatus(200)
        ->assertJsonPath('is_active', false);

    $this->assertDatabaseHas('wfd_definitions', [
        'id'        => $workflow->id,
        'is_active' => false,
    ]);
});

test('can toggle workflow back to active', function () {
    $user     = workflowUser('admin');
    $workflow = makeWorkflowForUser($user->id, ['is_active' => false]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/workflows/{$workflow->id}/toggle");

    $response->assertStatus(200)
        ->assertJsonPath('is_active', true);
});

// ─── Execution History ─────────────────────────────────────────────────────────

test('can list execution history for a workflow', function () {
    $user     = workflowUser('admin');
    $workflow = makeWorkflowForUser($user->id);

    WorkflowExecution::create([
        'tenant_id'   => $user->id,
        'workflow_id' => $workflow->id,
        'status'      => 'completed',
        'started_at'  => now(),
    ]);
    WorkflowExecution::create([
        'tenant_id'   => $user->id,
        'workflow_id' => $workflow->id,
        'status'      => 'failed',
        'started_at'  => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/workflows/{$workflow->id}/executions");

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('execution history is paginated', function () {
    $user     = workflowUser('admin');
    $workflow = makeWorkflowForUser($user->id);

    for ($i = 0; $i < 5; $i++) {
        WorkflowExecution::create([
            'tenant_id'   => $user->id,
            'workflow_id' => $workflow->id,
            'status'      => 'completed',
            'started_at'  => now(),
        ]);
    }

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/workflows/{$workflow->id}/executions?per_page=2");

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data', 'current_page', 'total', 'per_page', 'last_page']);
});

// ─── Manual Trigger ────────────────────────────────────────────────────────────

test('manual trigger creates an execution record', function () {
    $user     = workflowUser('admin');
    $workflow = makeWorkflowForUser($user->id);
    makeAction($workflow->id);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/workflows/trigger', [
            'workflow_id' => $workflow->id,
            'context'     => ['contact_id' => 42],
        ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('wfd_executions', [
        'workflow_id' => $workflow->id,
        'tenant_id'   => $user->id,
    ]);
});

test('manual trigger sets execution status to completed', function () {
    $user     = workflowUser('admin');
    $workflow = makeWorkflowForUser($user->id);
    makeAction($workflow->id);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/workflows/trigger', [
            'workflow_id' => $workflow->id,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'completed');
});

test('manual trigger creates execution logs for each action', function () {
    $user     = workflowUser('admin');
    $workflow = makeWorkflowForUser($user->id);
    makeAction($workflow->id, ['order' => 0]);
    makeAction($workflow->id, ['action_type' => 'send_sms', 'order' => 1]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/workflows/trigger', [
            'workflow_id' => $workflow->id,
        ]);

    $response->assertStatus(201);
    $this->assertDatabaseCount('wfd_execution_logs', 2);
});

test('manual trigger returns 422 when workflow_id is missing', function () {
    $user = workflowUser('admin');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/workflows/trigger', [
            'context' => ['foo' => 'bar'],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['workflow_id']);
});

test('manual trigger returns 404 for workflow not belonging to tenant', function () {
    $user    = workflowUser('admin');
    $other   = User::factory()->create(['id' => 9999]);
    $foreign = makeWorkflowForUser($other->id + 1000);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/workflows/trigger', [
            'workflow_id' => $foreign->id,
        ]);

    $response->assertStatus(404);
});

// ─── Tenant Isolation ──────────────────────────────────────────────────────────

test('tenant A cannot see tenant B workflows', function () {
    $userA = workflowUser('admin');
    makeWorkflowForUser($userA->id, ['name' => 'Tenant A Workflow']);

    $userB = User::factory()->create();
    makeWorkflowForUser($userB->id + 1000, ['name' => 'Tenant B Workflow']);

    $response = $this->actingAs($userA, 'sanctum')
        ->getJson('/api/v1/workflows');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Tenant A Workflow');
});

test('tenant A cannot update tenant B workflow', function () {
    $userA = workflowUser('admin');
    $userB = User::factory()->create();

    $foreignWorkflow = makeWorkflowForUser($userB->id + 1000);

    $response = $this->actingAs($userA, 'sanctum')
        ->putJson("/api/v1/workflows/{$foreignWorkflow->id}", ['name' => 'Hacked']);

    $response->assertStatus(404);
});

test('tenant A cannot delete tenant B workflow', function () {
    $userA = workflowUser('admin');
    $userB = User::factory()->create();

    $foreignWorkflow = makeWorkflowForUser($userB->id + 1000);

    $response = $this->actingAs($userA, 'sanctum')
        ->deleteJson("/api/v1/workflows/{$foreignWorkflow->id}");

    $response->assertStatus(404);
});
