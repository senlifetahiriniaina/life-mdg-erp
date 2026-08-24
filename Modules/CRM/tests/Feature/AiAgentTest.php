<?php

declare(strict_types=1);

use Modules\CRM\Models\AiAgent;
use Modules\CRM\Models\AiAgentRun;
use Modules\CRM\Services\AiAgentService;

describe('CRM AI Agents', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->service = app(AiAgentService::class);
    });

    // ─── Model: AiAgent ───────────────────────────────────────────────────────

    describe('AiAgent model', function () {
        test('isActive returns true when agent is active', function () {
            $agent = AiAgent::factory()->create(['is_active' => true]);
            expect($agent->isActive())->toBeTrue();
        });

        test('isActive returns false when agent is inactive', function () {
            $agent = AiAgent::factory()->create(['is_active' => false]);
            expect($agent->isActive())->toBeFalse();
        });

        test('shouldTrigger returns true when trigger type matches and agent is active', function () {
            $agent = AiAgent::factory()->create(['trigger_type' => 'schedule', 'is_active' => true]);
            expect($agent->shouldTrigger('schedule'))->toBeTrue();
        });

        test('shouldTrigger returns false when trigger type does not match', function () {
            $agent = AiAgent::factory()->create(['trigger_type' => 'schedule', 'is_active' => true]);
            expect($agent->shouldTrigger('event'))->toBeFalse();
        });

        test('shouldTrigger returns false when agent is inactive', function () {
            $agent = AiAgent::factory()->create(['trigger_type' => 'schedule', 'is_active' => false]);
            expect($agent->shouldTrigger('schedule'))->toBeFalse();
        });

        test('incrementRunCount increments run_count and sets last_run_at', function () {
            $agent = AiAgent::factory()->create(['run_count' => 0, 'last_run_at' => null]);
            $agent->incrementRunCount();
            $agent->refresh();

            expect($agent->run_count)->toBe(1);
            expect($agent->last_run_at)->not->toBeNull();
        });

        test('getConditions returns empty array when conditions is null', function () {
            $agent = AiAgent::factory()->create(['conditions' => null]);
            expect($agent->getConditions())->toBe([]);
        });

        test('getConditions returns conditions array when set', function () {
            $conditions = [['field' => 'score', 'operator' => 'gt', 'value' => 50]];
            $agent = AiAgent::factory()->create(['conditions' => $conditions]);
            expect($agent->getConditions())->toBe($conditions);
        });

        test('getActionConfig returns empty array when action_config is null', function () {
            $agent = AiAgent::factory()->create(['action_config' => null]);
            expect($agent->getActionConfig())->toBe([]);
        });

        test('getActionConfig returns action_config array when set', function () {
            $config = ['subject' => 'Hello', 'body' => 'World'];
            $agent = AiAgent::factory()->create(['action_config' => $config]);
            expect($agent->getActionConfig())->toBe($config);
        });

        test('agent has many runs', function () {
            $agent = AiAgent::factory()->create();
            AiAgentRun::factory()->count(3)->create(['agent_id' => $agent->id]);

            expect($agent->runs)->toHaveCount(3);
        });
    });

    // ─── Model: AiAgentRun ────────────────────────────────────────────────────

    describe('AiAgentRun model', function () {
        test('isSuccess returns true for success status', function () {
            $run = AiAgentRun::factory()->success()->create();
            expect($run->isSuccess())->toBeTrue();
        });

        test('isSuccess returns false for non-success status', function () {
            $run = AiAgentRun::factory()->failed()->create();
            expect($run->isSuccess())->toBeFalse();
        });

        test('isFailed returns true for failed status', function () {
            $run = AiAgentRun::factory()->failed()->create();
            expect($run->isFailed())->toBeTrue();
        });

        test('isFailed returns false for non-failed status', function () {
            $run = AiAgentRun::factory()->success()->create();
            expect($run->isFailed())->toBeFalse();
        });

        test('wasSkipped returns true for skipped status', function () {
            $run = AiAgentRun::factory()->skipped()->create();
            expect($run->wasSkipped())->toBeTrue();
        });

        test('wasSkipped returns false for non-skipped status', function () {
            $run = AiAgentRun::factory()->success()->create();
            expect($run->wasSkipped())->toBeFalse();
        });

        test('run belongs to agent', function () {
            $agent = AiAgent::factory()->create();
            $run = AiAgentRun::factory()->create(['agent_id' => $agent->id]);

            expect($run->agent)->toBeInstanceOf(AiAgent::class);
            expect($run->agent->id)->toBe($agent->id);
        });
    });

    // ─── Service ──────────────────────────────────────────────────────────────

    describe('AiAgentService', function () {
        test('createAgent creates and returns an agent', function () {
            $agent = $this->service->createAgent([
                'name' => 'Test Agent',
                'trigger_type' => 'manual',
                'action_type' => 'add_note',
            ]);

            expect($agent)->toBeInstanceOf(AiAgent::class);
            expect($agent->name)->toBe('Test Agent');
            expect($agent->exists)->toBeTrue();
        });

        test('runAgent returns an AiAgentRun with success status', function () {
            $agent = AiAgent::factory()->create([
                'action_type' => 'add_note',
                'trigger_type' => 'manual',
            ]);

            $run = $this->service->runAgent($agent, 'Contact', 1);

            expect($run)->toBeInstanceOf(AiAgentRun::class);
            expect($run->status)->toBe('success');
            expect($run->entity_type)->toBe('Contact');
            expect($run->entity_id)->toBe(1);
            expect($run->result)->toHaveKey('action');
        });

        test('runAgent with add_note creates a crm_activity of type note', function () {
            $agent = AiAgent::factory()->create([
                'action_type' => 'add_note',
                'trigger_type' => 'manual',
                'created_by' => $this->user->id,
            ]);

            // Chantier 38.3: runAgent() now only writes subject_type/subject_id when the
            // entity_type is a real, lowercase, allowlisted alias (contact/account/lead/
            // opportunity) — matching the same convention ActivityController's own
            // subject_type validation already uses. The old capitalized 'Contact' is exactly
            // the kind of un-mapped string the fix stops writing.
            $this->service->runAgent($agent, 'contact', 42);

            $this->assertDatabaseHas('crm_activities', [
                'type' => 'note',
                'subject_type' => 'contact',
                'subject_id' => 42,
            ]);
        });

        test('runAgent with create_task creates a crm_activity of type task', function () {
            $agent = AiAgent::factory()->create([
                'action_type' => 'create_task',
                'trigger_type' => 'manual',
                'created_by' => $this->user->id,
            ]);

            $this->service->runAgent($agent, 'lead', 99);

            $this->assertDatabaseHas('crm_activities', [
                'type' => 'task',
                'subject_type' => 'lead',
                'subject_id' => 99,
            ]);
        });

        test('runAgent silently drops an un-allowlisted entity_type rather than writing it raw', function () {
            // Chantier 38.3: locks in the fix for the confirmed information-disclosure IDOR —
            // an arbitrary FQCN passed as entity_type must never reach crm_activities.subject_type,
            // since ActivityController::show()'s load('subject') would resolve it via Eloquent's
            // MorphTo and disclose the full attributes of whatever that class happens to be.
            $agent = AiAgent::factory()->create([
                'action_type' => 'add_note',
                'trigger_type' => 'manual',
                'created_by' => $this->user->id,
            ]);

            $this->service->runAgent($agent, 'App\\Models\\User', 1);

            $this->assertDatabaseHas('crm_activities', [
                'type' => 'note',
                'subject_type' => null,
                'subject_id' => null,
            ]);
            $this->assertDatabaseMissing('crm_activities', ['subject_type' => 'App\\Models\\User']);
        });

        test('runAgent increments agent run_count', function () {
            $agent = AiAgent::factory()->create(['run_count' => 0]);

            $this->service->runAgent($agent, 'Contact', 1);

            $agent->refresh();
            expect($agent->run_count)->toBe(1);
        });

        test('runAgent sets last_run_at on agent', function () {
            $agent = AiAgent::factory()->create(['last_run_at' => null]);

            $this->service->runAgent($agent, 'Contact', 1);

            $agent->refresh();
            expect($agent->last_run_at)->not->toBeNull();
        });

        test('runScheduledAgents runs all active scheduled agents', function () {
            AiAgent::factory()->count(3)->create([
                'trigger_type' => 'schedule',
                'is_active' => true,
                'action_type' => 'add_note',
                'created_by' => $this->user->id,
            ]);
            // inactive — should not run
            AiAgent::factory()->create([
                'trigger_type' => 'schedule',
                'is_active' => false,
            ]);

            $runs = $this->service->runScheduledAgents();

            expect($runs)->toHaveCount(3);
            foreach ($runs as $run) {
                expect($run)->toBeInstanceOf(AiAgentRun::class);
            }
        });

        test('runEventAgents runs matching event agents', function () {
            AiAgent::factory()->count(2)->create([
                'trigger_type' => 'event',
                'trigger_config' => ['event' => 'contact.created'],
                'is_active' => true,
                'action_type' => 'add_note',
                'created_by' => $this->user->id,
            ]);
            // Different event — should not run
            AiAgent::factory()->create([
                'trigger_type' => 'event',
                'trigger_config' => ['event' => 'lead.updated'],
                'is_active' => true,
                'action_type' => 'add_note',
                'created_by' => $this->user->id,
            ]);

            $runs = $this->service->runEventAgents('contact.created', 'Contact', 5);

            expect($runs)->toHaveCount(2);
        });

        test('getAgentHistory returns recent runs', function () {
            $agent = AiAgent::factory()->create();
            AiAgentRun::factory()->count(5)->create(['agent_id' => $agent->id]);

            $history = $this->service->getAgentHistory($agent, 10);

            expect($history)->toHaveCount(5);
        });

        test('getAgentStats returns expected keys', function () {
            $agent = AiAgent::factory()->create(['run_count' => 0]);

            $stats = $this->service->getAgentStats($agent);

            expect($stats)->toHaveKeys(['total_runs', 'success_rate', 'last_run_at', 'avg_duration_ms']);
        });

        test('getAgentStats calculates correct success rate', function () {
            $agent = AiAgent::factory()->create();
            AiAgentRun::factory()->success()->count(3)->create(['agent_id' => $agent->id]);
            AiAgentRun::factory()->failed()->count(1)->create(['agent_id' => $agent->id]);

            $stats = $this->service->getAgentStats($agent);

            expect($stats['total_runs'])->toBe(4);
            expect($stats['success_rate'])->toBe(75.0);
        });

        test('toggleAgent toggles is_active state', function () {
            $agent = AiAgent::factory()->create(['is_active' => true]);

            $updated = $this->service->toggleAgent($agent);

            expect($updated->is_active)->toBeFalse();

            $updated2 = $this->service->toggleAgent($updated);

            expect($updated2->is_active)->toBeTrue();
        });

        test('deleteAgent removes agent and all its runs', function () {
            $agent = AiAgent::factory()->create();
            AiAgentRun::factory()->count(3)->create(['agent_id' => $agent->id]);

            $agentId = $agent->id;
            $this->service->deleteAgent($agent);

            $this->assertDatabaseMissing('crm_ai_agents', ['id' => $agentId]);
            $this->assertDatabaseMissing('crm_ai_agent_runs', ['agent_id' => $agentId]);
        });
    });

    // ─── API Endpoints ────────────────────────────────────────────────────────

    describe('API', function () {
        test('GET /api/v1/crm/ai-agents returns paginated list', function () {
            AiAgent::factory()->count(3)->create();

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson('/api/v1/crm/ai-agents');

            expect($response->status())->toBe(200);
            expect($response->json('data'))->toHaveCount(3);
        });

        test('POST /api/v1/crm/ai-agents creates an agent and returns 201', function () {
            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/crm/ai-agents', [
                    'name' => 'Auto Note Agent',
                    'trigger_type' => 'manual',
                    'action_type' => 'add_note',
                ]);

            expect($response->status())->toBe(201);
            expect($response->json('name'))->toBe('Auto Note Agent');
            expect($response->json('trigger_type'))->toBe('manual');
        });

        test('POST /api/v1/crm/ai-agents validates required fields', function () {
            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/crm/ai-agents', []);

            expect($response->status())->toBe(422);
        });

        test('GET /api/v1/crm/ai-agents/{id} returns agent', function () {
            $agent = AiAgent::factory()->create();

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson("/api/v1/crm/ai-agents/{$agent->id}");

            expect($response->status())->toBe(200);
            expect($response->json('id'))->toBe($agent->id);
        });

        test('PUT /api/v1/crm/ai-agents/{id} updates agent', function () {
            $agent = AiAgent::factory()->create(['name' => 'Old Name']);

            $response = $this->actingAs($this->user, 'sanctum')
                ->putJson("/api/v1/crm/ai-agents/{$agent->id}", [
                    'name' => 'New Name',
                ]);

            expect($response->status())->toBe(200);
            expect($response->json('name'))->toBe('New Name');
        });

        test('DELETE /api/v1/crm/ai-agents/{id} returns 204', function () {
            $agent = AiAgent::factory()->create();

            $response = $this->actingAs($this->user, 'sanctum')
                ->deleteJson("/api/v1/crm/ai-agents/{$agent->id}");

            expect($response->status())->toBe(204);
            $this->assertDatabaseMissing('crm_ai_agents', ['id' => $agent->id]);
        });

        test('POST /api/v1/crm/ai-agents/{id}/run runs agent and returns run', function () {
            $agent = AiAgent::factory()->create([
                'action_type' => 'add_note',
                'created_by' => $this->user->id,
                'tenant_id' => $this->user->company_id,
            ]);
            // Chantier 38.3: run()'s entity_type/entity_id are now validated against a real
            // contact/account/lead/opportunity allowlist + same-company ownership (closes a
            // real cross-model information-disclosure IDOR — see AiAgentController::run()'s
            // own docblock) — the old 'Contact'/id=1-with-no-backing-record payload would now
            // correctly 404. company_id is left unset on both sides (null === null) to match
            // this test file's existing single-tenant convention.
            $contact = \Modules\CRM\Models\Contact::factory()->create();

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson("/api/v1/crm/ai-agents/{$agent->id}/run", [
                    'entity_type' => 'contact',
                    'entity_id' => $contact->id,
                ]);

            expect($response->status())->toBe(200);
            expect($response->json('status'))->toBe('success');
            expect($response->json('entity_type'))->toBe('contact');
        });

        test('GET /api/v1/crm/ai-agents/{id}/history returns run history', function () {
            $agent = AiAgent::factory()->create();
            AiAgentRun::factory()->count(5)->create(['agent_id' => $agent->id]);

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson("/api/v1/crm/ai-agents/{$agent->id}/history");

            expect($response->status())->toBe(200);
            expect($response->json())->toHaveCount(5);
        });

        test('GET /api/v1/crm/ai-agents/{id}/stats returns statistics', function () {
            $agent = AiAgent::factory()->create();

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson("/api/v1/crm/ai-agents/{$agent->id}/stats");

            expect($response->status())->toBe(200);
            expect($response->json())->toHaveKeys(['total_runs', 'success_rate', 'last_run_at', 'avg_duration_ms']);
        });

        test('POST /api/v1/crm/ai-agents/{id}/toggle toggles active state', function () {
            $agent = AiAgent::factory()->create(['is_active' => true]);

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson("/api/v1/crm/ai-agents/{$agent->id}/toggle");

            expect($response->status())->toBe(200);
            expect($response->json('is_active'))->toBeFalse();
        });

        test('POST /api/v1/crm/ai-agents/run-scheduled runs scheduled agents', function () {
            AiAgent::factory()->count(2)->create([
                'trigger_type' => 'schedule',
                'is_active' => true,
                'action_type' => 'add_note',
                'created_by' => $this->user->id,
            ]);

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/crm/ai-agents/run-scheduled');

            expect($response->status())->toBe(200);
            expect($response->json('ran'))->toBe(2);
        });
    });
});

describe('AI Agent API unauthenticated', function () {
    test('unauthenticated requests return 401', function () {
        $response = $this->getJson('/api/v1/crm/ai-agents');

        expect($response->status())->toBe(401);
    });
});
