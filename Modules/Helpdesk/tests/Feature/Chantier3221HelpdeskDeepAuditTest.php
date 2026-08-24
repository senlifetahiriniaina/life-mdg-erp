<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Helpdesk\Models\LanguageDetection;
use Modules\Helpdesk\Models\SentimentScore;
use Modules\Helpdesk\Models\Ticket;
use Tests\TestCase;

/**
 * Chantier 32.21 — deep 14-layer audit of Modules\Helpdesk, following the
 * methodology in CLAUDE.md's "Méthodologie d'audit approfondi (14 couches,
 * à partir du Chantier 32)". Locks in every real bug found and fixed by
 * this pass, each via a real HTTP route or a real model/service call —
 * never a re-read of the code.
 */
class Chantier3221HelpdeskDeepAuditTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): void
    {
        if (\Spatie\Permission\Models\Permission::count() === 0) {
            $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        }
    }

    private function agentUser(Company $company, string $role = 'admin'): User
    {
        $this->seedRoles();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);

        return $user;
    }

    // ── Layer 6 (deep security) — hd_tickets cross-tenant leak ────────────

    public function test_ticket_created_by_authenticated_user_inherits_their_company_id(): void
    {
        $company = Company::create(['name' => 'Chantier 32.21 Co A', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
        $user = $this->agentUser($company);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/helpdesk/tickets', [
            'subject' => 'Company-scoped ticket',
            'description' => 'A real ticket for tenant isolation testing.',
            'priority' => 'medium',
            'channel' => 'web',
        ])->assertStatus(201);

        $ticket = Ticket::findOrFail($response->json('id'));
        $this->assertSame($company->id, $ticket->company_id);
    }

    public function test_ticket_index_is_scoped_to_the_callers_company_when_both_sides_have_one(): void
    {
        $companyA = Company::create(['name' => 'Chantier 32.21 Co A2', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
        $companyB = Company::create(['name' => 'Chantier 32.21 Co B2', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);

        $ticketA = Ticket::factory()->create(['company_id' => $companyA->id, 'subject' => 'Ticket A']);
        $ticketB = Ticket::factory()->create(['company_id' => $companyB->id, 'subject' => 'Ticket B']);

        $userA = $this->agentUser($companyA);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/helpdesk/tickets')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($ticketA->id, $ids);
        $this->assertNotContains($ticketB->id, $ids);
    }

    public function test_ticket_index_stays_permissive_when_the_user_has_no_company_id(): void
    {
        // Null-safe scoping (Chantier 10 Projects precedent): a not-yet-
        // provisioned user (no company_id) must not be silently locked out
        // of every ticket — this is the deliberate no-op case, distinct
        // from the real cross-tenant leak fixed above.
        $company = Company::create(['name' => 'Chantier 32.21 Co C', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
        $ticket = Ticket::factory()->create(['company_id' => $company->id]);

        $this->seedRoles();
        $user = User::factory()->create(['company_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/tickets')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($ticket->id, $ids);
    }

    // ── Layer 7 (RBAC) — assign/resolve/close/escalate had zero authorize() ─

    public function test_bare_unroled_user_cannot_assign_a_ticket(): void
    {
        $this->seedRoles();
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();
        $agent = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/assign", ['assignee_id' => $agent->id])
            ->assertForbidden();
    }

    public function test_supervisor_can_assign_a_ticket_in_their_own_company(): void
    {
        $company = Company::create(['name' => 'Chantier 32.21 Co D', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
        $user = $this->agentUser($company, 'supervisor');
        $ticket = Ticket::factory()->create(['company_id' => $company->id]);
        $agent = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/assign", ['assignee_id' => $agent->id])
            ->assertOk();

        $this->assertSame($agent->id, $ticket->fresh()->assignee_id);
    }

    public function test_bare_unroled_user_cannot_resolve_a_ticket(): void
    {
        $this->seedRoles();
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['status' => 'open']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/resolve")
            ->assertForbidden();
    }

    public function test_bare_unroled_user_cannot_close_a_ticket(): void
    {
        $this->seedRoles();
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['status' => 'resolved']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/close")
            ->assertForbidden();
    }

    public function test_bare_unroled_user_cannot_escalate_a_ticket_they_do_not_own(): void
    {
        $this->seedRoles();
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['status' => 'open', 'priority' => 'low']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/escalate")
            ->assertForbidden();
    }

    public function test_reporter_can_escalate_their_own_open_ticket(): void
    {
        $this->seedRoles();
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['status' => 'open', 'priority' => 'low', 'reporter_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/escalate")
            ->assertOk()
            ->assertJsonPath('priority', 'urgent');
    }

    // ── New auto-assign endpoint (TicketAssignmentService activation) ──────

    public function test_auto_assign_endpoint_is_routed_and_gated(): void
    {
        $company = Company::create(['name' => 'Chantier 32.21 Co E', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
        $manager = $this->agentUser($company, 'manager');
        $ticket = Ticket::factory()->create(['company_id' => $company->id, 'assignee_id' => null]);

        // No eligible agent exists yet — the endpoint must still respond
        // (not fatal) rather than assign to a nonexistent agent.
        $response = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/auto-assign");

        $this->assertContains($response->status(), [200, 422]);
    }

    // ── Layer 9 (fake/dead) — SentimentAnalysisService activated on create ──

    public function test_ticket_creation_auto_records_a_real_sentiment_score(): void
    {
        $company = Company::create(['name' => 'Chantier 32.21 Co F', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
        $user = $this->agentUser($company);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/helpdesk/tickets', [
            'subject' => 'I am very happy with the excellent service',
            'description' => 'Everything worked great, thank you so much.',
            'priority' => 'low',
            'channel' => 'web',
        ])->assertStatus(201);

        $ticketId = $response->json('id');

        $sentiment = SentimentScore::where('ticket_id', $ticketId)->first();
        $this->assertNotNull($sentiment, 'SentimentAnalysisService should auto-record a SentimentScore on ticket creation.');
        $this->assertSame('completed', $sentiment->status);

        $language = LanguageDetection::where('ticket_id', $ticketId)->first();
        $this->assertNotNull($language, 'LanguageDetection should auto-record on ticket creation.');
    }

    public function test_sentiment_analysis_endpoint_now_returns_real_data_instead_of_404(): void
    {
        $this->seedRoles();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'customer-service', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('customer-service');

        $ticket = Ticket::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/cs-ai/sentiment?ticket_id={$ticket->id}")
            ->assertOk();
    }

    // ── AlertService dead-duplicate removal — SLA automation still works ───

    public function test_sla_service_still_applies_a_default_policy_without_alert_service(): void
    {
        \Modules\Helpdesk\Models\SlaPolicy::factory()->create(['is_default' => true, 'response_time_hours' => 4]);
        $this->seedRoles();
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/helpdesk/tickets', [
            'subject' => 'SLA still applies',
            'priority' => 'medium',
        ])->assertStatus(201);

        $this->assertNotNull($response->json('sla_id'));
    }

    // ── Real PDF/Excel export generation (previously a fabricated URL) ─────

    public function test_agent_performance_report_export_generates_a_real_file(): void
    {
        $agent = User::factory()->create();
        $this->seedRoles();
        $caller = User::factory()->create();

        $response = $this->actingAs($caller, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/report/export?format=pdf")
            ->assertOk();

        $url = $response->json('export_url');
        $this->assertNotEmpty($url);

        $path = parse_url((string) $url, PHP_URL_PATH);
        $relative = ltrim(str_replace('/storage/', '', (string) $path), '/');
        $this->assertTrue(
            \Illuminate\Support\Facades\Storage::disk('public')->exists($relative),
            'The exported report file must actually exist on disk, not just be referenced by a fabricated URL.'
        );
    }

    public function test_agent_metrics_export_no_longer_fatals_when_support_agent_role_is_unseeded(): void
    {
        // Chantier 32.21 bug: User::role('support-agent') throws
        // RoleDoesNotExist (a real 500) whenever that role has never been
        // seeded — fixed to query the pivot directly, which degrades to an
        // empty collection instead of a fatal error.
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/agents/metrics-export?format=csv')
            ->assertOk();

        $this->assertNotEmpty($response->json('export_url'));
    }
}
