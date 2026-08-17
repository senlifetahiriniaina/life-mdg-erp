<?php

namespace Modules\Helpdesk\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Helpdesk\Models\Ticket;
use Tests\TestCase;

/**
 * Chantier 8.2: Helpdesk cross-layer audit remediation.
 *
 * - CustomerServiceAIController's 21 cs-ai endpoints now enforce
 *   CustomerServiceAIPolicy (previously wired up but never called — any
 *   authenticated user could reach every endpoint).
 * - SlaAutomationWebController exposes the real, already-live SlaController
 *   (policies/breaches/compliance/performance) that had no page before.
 */
class Chantier82HelpdeskTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_permission_cannot_view_sentiment_analysis(): void
    {
        // Seed permissions (so helpdesk.sentiment.view really exists) but assign
        // no role — CustomerServiceAIController now enforces CustomerServiceAIPolicy
        // (previously allowed any authenticated user).
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $ticket = Ticket::factory()->create();

        $response = $this->getJson("/api/v1/helpdesk/cs-ai/sentiment?ticket_id={$ticket->id}");

        $response->assertForbidden();
    }

    public function test_user_with_permission_can_view_sentiment_analysis(): void
    {
        $this->actingAsUser('customer-service');

        $ticket = Ticket::factory()->create();

        $response = $this->getJson("/api/v1/helpdesk/cs-ai/sentiment?ticket_id={$ticket->id}");

        // No SentimentScore row exists for this ticket, so the controller's own
        // "not found" branch fires — the point here is that authorization let the
        // request through at all (not a 403), not that data exists.
        $response->assertStatus(404);
    }

    public function test_user_without_permission_cannot_create_routing_rule(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/helpdesk/cs-ai/routing-rules', [
            'name' => 'Unauthorized Rule',
            'rule_type' => 'sentiment_based',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_create_routing_rule(): void
    {
        $this->actingAsUser('admin');

        $response = $this->postJson('/api/v1/helpdesk/cs-ai/routing-rules', [
            'name' => 'Escalate negative sentiment',
            'rule_type' => 'sentiment_based',
            'sentiment_trigger' => 'negative',
            'target_queue' => 'priority',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('cs_routing_rules', ['name' => 'Escalate negative sentiment']);
    }

    public function test_sla_automation_page_renders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/helpdesk/sla-automation');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Helpdesk/SlaAutomation/Index', false));
    }

    public function test_sla_policies_endpoint_seeds_and_returns_default_policies(): void
    {
        $this->actingAsUser('admin');

        $response = $this->getJson('/api/v1/helpdesk/sla/policies');

        $response->assertOk();
        $this->assertNotEmpty($response->json());
    }

    public function test_sla_compliance_stats_endpoint_returns_structure(): void
    {
        $this->actingAsUser('admin');

        $response = $this->getJson('/api/v1/helpdesk/sla/stats/compliance');

        $response->assertOk()
            ->assertJsonStructure(['total_tickets_checked', 'compliant', 'breached', 'compliance_rate']);
    }

    public function test_bot_widget_page_renders_without_authentication(): void
    {
        // Deliberately anonymous — this widget is meant to be embedded on an
        // external site and calls the already-public AnswerBotController
        // endpoints (helpdesk/bot/ask, .../deflect), so the page itself must
        // not require auth either.
        $response = $this->get('/helpdesk/bot/widget');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Helpdesk/Bot/Widget', false));
    }
}
