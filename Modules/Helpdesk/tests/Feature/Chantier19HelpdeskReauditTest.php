<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Helpdesk\Models\ChatSession;
use Modules\Helpdesk\Models\ForumPost;
use Modules\Helpdesk\Models\KbPortalArticle;
use Modules\Helpdesk\Models\KbPortalCategory;
use Modules\Helpdesk\Models\SlaPolicy;
use Modules\Helpdesk\Models\Team;
use Modules\Helpdesk\Models\Ticket;
use Modules\HR\Models\Employee;
use Tests\TestCase;

/**
 * Chantier 19 Lot 2 (Helpdesk) — second, execution-based re-verification
 * pass (route -> controller -> model -> API -> view -> security/RBAC),
 * following the methodology established by Chantier 18/19 Lot 1: code
 * reading alone cannot detect a query against a relation that doesn't
 * exist, or a route-model-binding key mismatch — only actually calling the
 * endpoint does. All bugs below were found by exercising the real HTTP
 * routes with realistic seeded data, not by reading the code.
 */
class Chantier19HelpdeskReauditTest extends TestCase
{
    use RefreshDatabase;

    private function seedDefaultSla(): SlaPolicy
    {
        return SlaPolicy::firstOrCreate(
            ['name' => 'Standard'],
            ['response_time_minutes' => 240, 'resolution_time_minutes' => 1440, 'is_default' => true, 'business_hours' => null]
        );
    }

    /**
     * Headline bug: TicketService::createFromSource() wrote
     * $source::class (the raw FQCN, e.g. 'Modules\HR\Models\Employee') into
     * hd_tickets.source_type instead of $source->getMorphClass(). Once
     * HelpdeskServiceProvider registers a morph map, Eloquent's
     * MorphMany::getMorphClass() resolves to the short alias ('employee')
     * for any model whose class is a morph-map value — so
     * $employee->tickets() queried source_type='employee' against rows
     * that were actually written with the raw class name, and always came
     * back empty. raiseTicket() itself "worked" (Ticket::source() tolerates
     * either form), masking the bug — only the documented reverse relation
     * ($model->tickets, CLAUDE.md's own example) was broken, for every one
     * of the 8 HelpdeskLinkable modules.
     */
    public function test_helpdesk_linkable_reverse_relation_finds_the_ticket_it_raised(): void
    {
        $this->seedDefaultSla();
        $employee = Employee::factory()->create();

        $ticket = $employee->raiseTicket(['subject' => 'Facture erronée', 'priority' => 'high']);

        $this->assertSame('employee', $ticket->source_type);
        $this->assertSame($employee->id, $ticket->source_id);
        $this->assertTrue($employee->tickets()->whereKey($ticket->id)->exists());
        $this->assertCount(1, $employee->tickets);
        $this->assertInstanceOf(Employee::class, $ticket->source);
        $this->assertSame($employee->id, $ticket->source->id);
    }

    /** Same fix, exercised through the generic ticket endpoint's source_module/source_id path. */
    public function test_ticket_created_via_source_module_is_visible_on_reverse_relation(): void
    {
        $this->seedDefaultSla();
        $employee = Employee::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/helpdesk/tickets', [
            'subject' => 'Problème matériel',
            'source_module' => 'employee',
            'source_id' => $employee->id,
        ]);

        $response->assertCreated();
        $this->assertCount(1, $employee->fresh()->tickets);
    }

    /**
     * SlaService::apply() is supposed to run on every ticket-creation hook
     * (Ticket::booted()'s created listener) — confirmed still true, and
     * confirmed the ticket ends up with a real due date, not just a
     * non-null sla_id.
     */
    public function test_sla_is_applied_automatically_on_ticket_creation(): void
    {
        $sla = $this->seedDefaultSla();

        $ticket = Ticket::create(['subject' => 'Test SLA', 'reporter_id' => User::factory()->create()->id]);

        $this->assertSame($sla->id, $ticket->sla_id);
        $this->assertNotNull($ticket->sla_due_at);
        $this->assertFalse($ticket->sla_breached);
    }

    /**
     * KbPortalArticle had no getRouteKeyName()/resolveRouteBinding()
     * override, so implicit route-model binding only ever matched the
     * numeric id. Portal/Index.vue's openArticle() calls
     * GET .../kb/portal/articles/${article.slug || article.id} — every
     * real row always has a non-empty slug (KbPortalArticle::boot()'s
     * creating hook), so the frontend always sent the slug and every real
     * click on a public KB portal article 404'd.
     */
    public function test_kb_portal_article_is_reachable_by_slug_and_by_id(): void
    {
        $category = KbPortalCategory::factory()->create();
        $article = KbPortalArticle::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $this->assertNotEmpty($article->slug);

        $bySlug = $this->getJson("/api/v1/helpdesk/kb/portal/articles/{$article->slug}");
        $bySlug->assertOk()->assertJsonPath('id', $article->id);

        $byId = $this->getJson("/api/v1/helpdesk/kb/portal/articles/{$article->id}");
        $byId->assertOk()->assertJsonPath('id', $article->id);
    }

    /**
     * AgentPerformanceController::benchmarking()/coaching() both fatalled
     * on every real call: their chain (benchmarkAgainstTeam() /
     * generateCoachingRecommendations() -> calculateSentimentImprovement()
     * -> SentimentAnalysisService::trackSentimentEvolution()) called
     * $ticket->messages(), a relation that has never existed on Ticket
     * (only comments()) — the same bug class already fixed in
     * SatisfactionPredictionService/PredictiveEscalationService, missed
     * here. Confirmed via a real HTTP call with a real assigned ticket, not
     * just a code read.
     */
    public function test_agent_benchmarking_and_coaching_endpoints_do_not_fatal(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $agent = User::factory()->create();
        $team = Team::factory()->create();
        Ticket::factory()->create([
            'assignee_id' => $agent->id,
            'team_id' => $team->id,
            'status' => 'resolved',
        ]);

        $this->actingAs($admin, 'sanctum');

        $this->getJson("/api/v1/helpdesk/agents/{$agent->id}/benchmarking")->assertOk();
        $this->getJson("/api/v1/helpdesk/agents/{$agent->id}/coaching")->assertOk();
    }

    /**
     * The source_module/source_id allowlist (validated against
     * Relation::morphMap() in HelpdeskServiceProvider, never a raw client
     * class name) is re-confirmed actually enforced on a real request, not
     * just present in code.
     */
    public function test_arbitrary_source_module_alias_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/helpdesk/tickets', [
            'subject' => 'test',
            'source_module' => 'user',
            'source_id' => $user->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('source_module');
    }

    /**
     * CustomerServiceAIPolicy's loop-based Gate::define() registration
     * (HelpdeskServiceProvider::registerCustomerServiceAiGates()) is
     * re-confirmed still actually enforced end-to-end: a customer-service
     * agent can reach a cs-ai endpoint, an unrelated role cannot.
     */
    public function test_customer_service_role_can_reach_cs_ai_endpoint_others_cannot(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $agent = User::factory()->create();
        $agent->assignRole('customer-service');
        $this->actingAs($agent, 'sanctum');
        $this->getJson('/api/v1/helpdesk/cs-ai/response-templates')->assertOk();

        $outsider = User::factory()->create();
        $outsider->assignRole('sales-rep');
        $this->actingAs($outsider, 'sanctum');
        $this->getJson('/api/v1/helpdesk/cs-ai/response-templates')->assertForbidden();
    }

    /**
     * Basic smoke coverage for the web pages that had zero render-level
     * test coverage before this pass (only their API endpoints were
     * tested) — confirms each controller's real query actually executes
     * against the real schema, not just that the route exists.
     */
    public function test_helpdesk_web_index_pages_render(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/helpdesk/tickets')->assertOk();
        $this->get('/helpdesk/chat')->assertOk();
        $this->get('/helpdesk/portal')->assertOk();
        $this->get('/helpdesk/escalation')->assertOk();
        $this->get('/helpdesk/knowledge-base')->assertOk();
        $this->get('/helpdesk/csat')->assertOk();
        $this->get('/helpdesk/forum')->assertOk();
    }

    public function test_forum_show_page_renders_with_real_post_id_prop(): void
    {
        $user = User::factory()->create();
        $post = ForumPost::factory()->create();

        $response = $this->actingAs($user)->get("/helpdesk/forum/{$post->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Helpdesk/Forum/Show', false)->where('id', (string) $post->id));
    }

    public function test_chat_session_convert_to_ticket_end_to_end(): void
    {
        $this->seedDefaultSla();
        $agent = User::factory()->create();
        $this->actingAs($agent, 'sanctum');

        $session = ChatSession::factory()->create([
            'status' => 'active',
            'started_at' => now(),
            'closed_at' => null,
            'assigned_agent_id' => $agent->id,
            'ticket_id' => null,
            'metadata' => [],
        ]);

        $response = $this->postJson("/api/v1/helpdesk/chat/sessions/{$session->id}/convert-to-ticket");

        $response->assertCreated();
        $this->assertNotNull($response->json('ticket.id'));
    }
}
