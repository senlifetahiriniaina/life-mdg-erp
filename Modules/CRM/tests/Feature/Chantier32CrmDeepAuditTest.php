<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Account;
use Modules\CRM\Models\AiAgent;
use Modules\CRM\Models\CallLog;
use Modules\CRM\Models\Campaign;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\EmailSequence;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\OpportunityHistory;
use Modules\CRM\Models\Pipeline;
use Modules\CRM\Models\Quote;
use Modules\CRM\Models\Territory;
use Modules\CRM\Models\WebForm;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Chantier 32.15 — CRM 14-layer deep audit.
 *
 * Locks in every real bug found/fixed by this chantier's audit — see CLAUDE.md's own
 * "Chantier 32.15 — CRM" entry for the full narrative. Uses the same real-two-company HTTP
 * pattern already established by Chantier19CrmReauditTest.php/CrmTenantIsolationFollowupTest.php
 * for this module, extended to the newly-audited subsystems (Voip/CallLog, EmailSequence,
 * Quote/CPQ, Territory, WebForm, PipelineAnalytics, AiAgent, Activity subject IDOR,
 * OpportunityHistory, stage validation, the new duplicates endpoint, and the dead-code
 * deletions).
 */
function deepAuditUser(string $companySuffix, string $role = 'admin'): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

    $company = Company::create([
        'name' => "Chantier32.15 Co {$companySuffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->forceFill([
        'two_factor_enabled' => true,
        'google2fa_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
    ])->save();
    $user->assignRole($role);

    DB::table('tenant_modules')->updateOrInsert(
        ['tenant_id' => (string) $user->id, 'module' => 'CRM', 'department' => null],
        ['enabled' => true, 'settings' => '{}', 'updated_at' => now(), 'created_at' => now()]
    );

    return $user;
}

// ── Dead/fake code: deleted for real, not just documented ──────────────────────

test('the dead no-code workflow builder route no longer exists', function () {
    $user = deepAuditUser('WF');
    $token = $user->createToken('t')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/crm/workflows', ['name' => 'x', 'trigger_type' => 'manual'])
        ->assertNotFound();
});

test('the dead Workflow/Customer/Company CRM classes are gone', function () {
    expect(class_exists(\Modules\CRM\Http\Controllers\Api\WorkflowBuilderController::class))->toBeFalse()
        ->and(class_exists(\Modules\CRM\Models\Workflow::class))->toBeFalse()
        ->and(class_exists(\Modules\CRM\Models\Customer::class))->toBeFalse()
        ->and(class_exists(\Modules\CRM\Services\CustomerManagementService::class))->toBeFalse()
        ->and(class_exists(\Modules\CRM\Services\SalesOpportunityService::class))->toBeFalse()
        ->and(class_exists(\Modules\CRM\Models\Company::class))->toBeFalse();
});

test('crm_workflows/crm_customers/crm_companies tables are dropped', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('crm_workflows'))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Schema::hasTable('crm_workflow_nodes'))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Schema::hasTable('crm_workflow_edges'))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Schema::hasTable('crm_workflow_executions'))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Schema::hasTable('crm_customers'))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Schema::hasTable('crm_companies'))->toBeFalse();
});

// ── VoipController / CallLog — new tenant isolation ─────────────────────────────

test('call logs are scoped to the caller own company', function () {
    $userA = deepAuditUser('CLA');
    $userB = deepAuditUser('CLB');

    $logA = CallLog::factory()->create(['tenant_id' => $userA->company_id, 'phone_number' => '+261340000001']);
    CallLog::factory()->create(['tenant_id' => $userB->company_id, 'phone_number' => '+261340000002']);

    $tokenA = $userA->createToken('t')->plainTextToken;

    $response = $this->withToken($tokenA)->getJson('/api/v1/crm/voip/call-logs')->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($logA->id)
        ->and($ids->count())->toBe(1);
});

test('a call log cannot be viewed by another company', function () {
    $userA = deepAuditUser('CLC');
    $userB = deepAuditUser('CLD');

    $logB = CallLog::factory()->create(['tenant_id' => $userB->company_id]);

    $tokenA = $userA->createToken('t')->plainTextToken;

    $this->withToken($tokenA)
        ->getJson("/api/v1/crm/voip/call-logs/{$logB->id}")
        ->assertForbidden();
});

// ── EmailSequenceController — new tenant isolation ──────────────────────────────

test('email sequences are scoped to the caller own company', function () {
    $userA = deepAuditUser('ESA');
    $userB = deepAuditUser('ESB');

    $seqA = EmailSequence::factory()->create(['tenant_id' => $userA->company_id, 'name' => 'A seq']);
    EmailSequence::factory()->create(['tenant_id' => $userB->company_id, 'name' => 'B seq']);

    $tokenA = $userA->createToken('t')->plainTextToken;

    $response = $this->withToken($tokenA)->getJson('/api/v1/crm/email-sequences')->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($seqA->id)
        ->and($ids->count())->toBe(1);
});

test('a company cannot view or mutate another company email sequence', function () {
    $userA = deepAuditUser('ESC');
    $userB = deepAuditUser('ESD');

    $seqB = EmailSequence::factory()->create(['tenant_id' => $userB->company_id]);

    $tokenA = $userA->createToken('t')->plainTextToken;

    $this->withToken($tokenA)->getJson("/api/v1/crm/email-sequences/{$seqB->id}")->assertForbidden();
    $this->withToken($tokenA)->putJson("/api/v1/crm/email-sequences/{$seqB->id}", ['name' => 'x'])->assertForbidden();
    $this->withToken($tokenA)->deleteJson("/api/v1/crm/email-sequences/{$seqB->id}")->assertForbidden();
});

test('a newly created email sequence is tagged with the creator own company', function () {
    $user = deepAuditUser('ESE');
    $token = $user->createToken('t')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/crm/email-sequences', ['name' => 'New seq', 'trigger_type' => 'manual'])
        ->assertCreated();

    $this->assertDatabaseHas('crm_email_sequences', ['name' => 'New seq', 'tenant_id' => $user->company_id]);
});

// ── QuoteController / CpqService — new tenant isolation ─────────────────────────

test('quotes are scoped to the caller own company', function () {
    $userA = deepAuditUser('QA');
    $userB = deepAuditUser('QB');

    $quoteA = Quote::factory()->create(['tenant_id' => $userA->company_id, 'reference' => 'QT-A']);
    Quote::factory()->create(['tenant_id' => $userB->company_id, 'reference' => 'QT-B']);

    $tokenA = $userA->createToken('t')->plainTextToken;

    $response = $this->withToken($tokenA)->getJson('/api/v1/crm/quotes')->assertOk();
    $refs = collect($response->json('data'))->pluck('reference');

    expect($refs)->toContain('QT-A')->not->toContain('QT-B');
});

test('a quote cannot be viewed, duplicated, or exported by another company', function () {
    $userA = deepAuditUser('QC');
    $userB = deepAuditUser('QD');

    $quoteB = Quote::factory()->create(['tenant_id' => $userB->company_id]);

    $tokenA = $userA->createToken('t')->plainTextToken;

    $this->withToken($tokenA)->getJson("/api/v1/crm/quotes/{$quoteB->id}")->assertForbidden();
    $this->withToken($tokenA)->postJson("/api/v1/crm/quotes/{$quoteB->id}/duplicate")->assertForbidden();
    $this->withToken($tokenA)->getJson("/api/v1/crm/quotes/{$quoteB->id}/pdf")->assertForbidden();
});

test('a newly created quote is tagged with the creator own company and the pdf uses Ar not euros', function () {
    $user = deepAuditUser('QE');
    $token = $user->createToken('t')->plainTextToken;

    $create = $this->withToken($token)
        ->postJson('/api/v1/crm/quotes', [])
        ->assertCreated();

    $this->assertDatabaseHas('crm_quotes', ['id' => $create->json('id'), 'tenant_id' => $user->company_id]);

    $pdf = $this->withToken($token)->get("/api/v1/crm/quotes/{$create->json('id')}/pdf")->assertOk();
    expect($pdf->getContent())->toContain('Ar')->not->toContain('€');
});

// ── TerritoryController / TerritoryService / TerritoryForecastService — new tenant isolation ──

test('territories are scoped to the caller own company', function () {
    $userA = deepAuditUser('TA');
    $userB = deepAuditUser('TB');

    $terrA = Territory::factory()->create(['company_id' => $userA->company_id, 'name' => 'Territory A']);
    Territory::factory()->create(['company_id' => $userB->company_id, 'name' => 'Territory B']);

    $tokenA = $userA->createToken('t')->plainTextToken;

    $response = $this->withToken($tokenA)->getJson('/api/v1/crm/territories')->assertOk();
    $names = collect($response->json('data'))->pluck('name');

    expect($names)->toContain('Territory A')->not->toContain('Territory B');
});

test('a territory cannot be viewed, updated, deleted or forecast by another company', function () {
    $userA = deepAuditUser('TC');
    $userB = deepAuditUser('TD');

    $terrB = Territory::factory()->create(['company_id' => $userB->company_id]);

    $tokenA = $userA->createToken('t')->plainTextToken;

    $this->withToken($tokenA)->getJson("/api/v1/crm/territories/{$terrB->id}")->assertForbidden();
    $this->withToken($tokenA)->putJson("/api/v1/crm/territories/{$terrB->id}", ['name' => 'x'])->assertForbidden();
    $this->withToken($tokenA)->getJson("/api/v1/crm/territories/{$terrB->id}/forecast")->assertForbidden();
    $this->withToken($tokenA)->deleteJson("/api/v1/crm/territories/{$terrB->id}")->assertForbidden();
});

test('team quotas and coverage only aggregate the caller own company territories', function () {
    $userA = deepAuditUser('TE');
    $userB = deepAuditUser('TF');

    Territory::factory()->create(['company_id' => $userA->company_id, 'name' => 'Only A', 'is_active' => true]);
    Territory::factory()->create(['company_id' => $userB->company_id, 'name' => 'Only B', 'is_active' => true]);

    $tokenA = $userA->createToken('t')->plainTextToken;

    $quotas = $this->withToken($tokenA)->getJson('/api/v1/crm/territories/team-quotas')->assertOk();
    expect(collect($quotas->json())->pluck('territory_name'))->toContain('Only A')->not->toContain('Only B');

    $coverage = $this->withToken($tokenA)->getJson('/api/v1/crm/territory-management/coverage')->assertOk();
    expect($coverage->json('total'))->toBe(1);
});

test('opportunity assignment to a territory is blocked across companies', function () {
    $userA = deepAuditUser('TG');
    $userB = deepAuditUser('TH');

    $terrA = Territory::factory()->create(['company_id' => $userA->company_id]);
    $pipeline = Pipeline::factory()->create();
    $oppB = Opportunity::factory()->create(['pipeline_id' => $pipeline->id, 'tenant_id' => $userB->company_id]);

    $tokenA = $userA->createToken('t')->plainTextToken;

    // Company A's own territory, but trying to assign Company B's opportunity into it.
    $this->withToken($tokenA)
        ->postJson("/api/v1/crm/territories/{$terrA->id}/assign-opportunity", ['opportunity_id' => $oppB->id])
        ->assertForbidden();
});

// ── WebFormController — new tenant isolation + fixed Lead data-loss bug ─────────

test('web forms are scoped to the caller own company', function () {
    $userA = deepAuditUser('WFA');
    $userB = deepAuditUser('WFB');

    $formA = WebForm::factory()->create(['tenant_id' => $userA->company_id, 'name' => 'Form A', 'slug' => 'form-a']);
    WebForm::factory()->create(['tenant_id' => $userB->company_id, 'name' => 'Form B', 'slug' => 'form-b']);

    $tokenA = $userA->createToken('t')->plainTextToken;

    $response = $this->withToken($tokenA)->getJson('/api/v1/crm/forms')->assertOk();
    $names = collect($response->json('data'))->pluck('name');

    expect($names)->toContain('Form A')->not->toContain('Form B');

    $this->withToken($tokenA)->getJson('/api/v1/crm/forms/'.WebForm::where('slug', 'form-b')->first()->id)->assertForbidden();
});

test('public web form submission tags the resulting lead with the form own company and preserves email/phone/company', function () {
    $owner = deepAuditUser('WFC');

    $form = WebForm::factory()->create([
        'tenant_id' => $owner->company_id,
        'slug' => 'contact-us-32-15',
        'create_lead' => true,
        'is_active' => true,
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'required' => false],
            ['name' => 'company', 'label' => 'Company', 'type' => 'text', 'required' => false],
        ],
    ]);

    $this->postJson('/api/v1/crm/forms/contact-us-32-15/submit', [
        'name' => 'Jane Prospect',
        'email' => 'jane@example.com',
        'phone' => '+261340000099',
        'company' => 'Prospect SARL',
    ])->assertOk()->assertJsonPath('success', true);

    $this->assertDatabaseHas('crm_leads', [
        'title' => 'Jane Prospect',
        'email' => 'jane@example.com',
        'phone' => '+261340000099',
        'company' => 'Prospect SARL',
        'company_id' => $owner->company_id,
    ]);

    // And the real lead-listing endpoint, correctly company-scoped since Chantier 19, can now
    // actually surface it to the form's own company — confirming the fix closes the real,
    // previously-silent "web-form leads are invisible" bug end-to-end, not just at the DB row.
    $token = $owner->createToken('t')->plainTextToken;
    $list = $this->withToken($token)->getJson('/api/v1/crm/leads')->assertOk();
    expect(collect($list->json('data'))->pluck('title'))->toContain('Jane Prospect');
});

// ── PipelineAnalyticsController / PipelineAnalyticsService — new tenant isolation ──

test('pipeline analytics dashboard and win rate only aggregate the caller own company', function () {
    $userA = deepAuditUser('PAA');
    $userB = deepAuditUser('PAB');

    $pipeline = Pipeline::factory()->create();
    $oppA = Opportunity::factory()->create(['pipeline_id' => $pipeline->id, 'tenant_id' => $userA->company_id, 'amount' => 1000]);
    $oppB = Opportunity::factory()->create(['pipeline_id' => $pipeline->id, 'tenant_id' => $userB->company_id, 'amount' => 999999]);

    // Chantier 32.15: switching the acting user mid-test via ->withToken() alone does not
    // re-resolve Sanctum's guard within one test method (RequestGuard caches its first
    // resolved user for the life of the test's container) — the established, working pattern
    // for a genuine multi-user-in-one-test scenario in this module is
    // test()->actingAs($user, 'sanctum') per call, already used by
    // Chantier19CrmReauditTest.php elsewhere in this file's own describe blocks.
    $this->actingAs($userA, 'sanctum')->postJson('/api/v1/crm/pipeline-analytics/record-win', ['opportunity_id' => $oppA->id])->assertCreated();
    $this->actingAs($userB, 'sanctum')->postJson('/api/v1/crm/pipeline-analytics/record-win', ['opportunity_id' => $oppB->id])->assertCreated();

    // Company A's dashboard must reflect only its own single, small win — not company B's.
    $dashboard = $this->actingAs($userA, 'sanctum')->getJson('/api/v1/crm/pipeline-analytics/dashboard')->assertOk();
    expect($dashboard->json('win_rate'))->toEqual(100.0);

    $topPerformers = $this->actingAs($userA, 'sanctum')->getJson('/api/v1/crm/pipeline-analytics/top-performers')->assertOk();
    $totalValues = collect($topPerformers->json())->pluck('total_value');
    expect($totalValues)->not->toContain(999999.0);
});

test('a company cannot record a win or loss for another company opportunity', function () {
    $userA = deepAuditUser('PAC');
    $userB = deepAuditUser('PAD');

    $pipeline = Pipeline::factory()->create();
    $oppB = Opportunity::factory()->create(['pipeline_id' => $pipeline->id, 'tenant_id' => $userB->company_id]);

    $tokenA = $userA->createToken('t')->plainTextToken;

    $this->withToken($tokenA)
        ->postJson('/api/v1/crm/pipeline-analytics/record-win', ['opportunity_id' => $oppB->id])
        ->assertNotFound();
});

// ── AiAgentController — new tenant isolation + cross-tenant run() write vector ──

test('ai agents are scoped to the caller own company', function () {
    $userA = deepAuditUser('AIA');
    $userB = deepAuditUser('AIB');

    $agentA = AiAgent::factory()->create(['tenant_id' => $userA->company_id, 'name' => 'Agent A']);
    AiAgent::factory()->create(['tenant_id' => $userB->company_id, 'name' => 'Agent B']);

    $tokenA = $userA->createToken('t')->plainTextToken;

    $response = $this->withToken($tokenA)->getJson('/api/v1/crm/ai-agents')->assertOk();
    $names = collect($response->json('data'))->pluck('name');

    expect($names)->toContain('Agent A')->not->toContain('Agent B');
});

test('an agent cannot be run against another company by id', function () {
    $userA = deepAuditUser('AIC');
    $userB = deepAuditUser('AID');

    $agentB = AiAgent::factory()->create(['tenant_id' => $userB->company_id, 'action_type' => 'add_note']);

    $tokenA = $userA->createToken('t')->plainTextToken;

    $this->withToken($tokenA)
        ->postJson("/api/v1/crm/ai-agents/{$agentB->id}/run", ['entity_type' => 'contact', 'entity_id' => 1])
        ->assertForbidden();
});

test('an agent-created note is tagged with the owning agent own company', function () {
    $owner = deepAuditUser('AIE');
    $agent = AiAgent::factory()->create(['tenant_id' => $owner->company_id, 'action_type' => 'add_note']);
    $token = $owner->createToken('t')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/crm/ai-agents/{$agent->id}/run", ['entity_type' => 'contact', 'entity_id' => 1])
        ->assertOk();

    $this->assertDatabaseHas('crm_activities', ['type' => 'note', 'company_id' => $owner->company_id]);
});

// ── Activity subject_type IDOR — real allowlist + cross-company subject check ──

test('activity subject_type rejects an arbitrary class name', function () {
    $user = deepAuditUser('ACTA');
    $token = $user->createToken('t')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/crm/activities', [
            'type' => 'note',
            'title' => 'x',
            'subject_type' => \App\Models\User::class,
            'subject_id' => 1,
        ])
        ->assertUnprocessable();
});

test('activity subject_id must belong to the caller own company', function () {
    $userA = deepAuditUser('ACTB');
    $userB = deepAuditUser('ACTC');

    $contactB = Contact::factory()->create(['company_id' => $userB->company_id]);
    $tokenA = $userA->createToken('t')->plainTextToken;

    $this->withToken($tokenA)
        ->postJson('/api/v1/crm/activities', [
            'type' => 'note',
            'title' => 'x',
            'subject_type' => 'contact',
            'subject_id' => $contactB->id,
        ])
        ->assertNotFound();
});

test('activity subject_id linking a real own-company contact succeeds', function () {
    $user = deepAuditUser('ACTD');
    $contact = Contact::factory()->create(['company_id' => $user->company_id]);
    $token = $user->createToken('t')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/crm/activities', [
            'type' => 'note',
            'title' => 'x',
            'subject_type' => 'contact',
            'subject_id' => $contact->id,
        ])
        ->assertCreated();
});

// ── OpportunityHistoryController — missing authorize() fixed ────────────────────

test('opportunity history cannot be read across companies', function () {
    $userA = deepAuditUser('OHA');
    $userB = deepAuditUser('OHB');

    $pipeline = Pipeline::factory()->create();
    $oppB = Opportunity::factory()->create(['pipeline_id' => $pipeline->id, 'tenant_id' => $userB->company_id]);
    OpportunityHistory::factory()->create(['opportunity_id' => $oppB->id]);

    $tokenA = $userA->createToken('t')->plainTextToken;

    $this->withToken($tokenA)
        ->getJson("/api/v1/crm/opportunities/{$oppB->id}/history")
        ->assertForbidden();
});

// ── ContactEmailController::sendBulk — scoped to caller own contacts ────────────

test('bulk contact email only sends to the caller own company contacts', function () {
    $userA = deepAuditUser('CEA');
    $userB = deepAuditUser('CEB');

    $ownContact = Contact::factory()->create(['company_id' => $userA->company_id, 'email' => 'own@example.com', 'status' => 'active']);
    $foreignContact = Contact::factory()->create(['company_id' => $userB->company_id, 'email' => 'foreign@example.com', 'status' => 'active']);

    $tokenA = $userA->createToken('t')->plainTextToken;

    $response = $this->withToken($tokenA)
        ->postJson('/api/v1/crm/contacts/email/bulk', [
            'contact_ids' => [$ownContact->id, $foreignContact->id],
            'template_code' => 'welcome',
        ])
        ->assertOk();

    // Only the caller's own contact should have been counted as a real send target.
    expect($response->json('sent'))->toBe(1);
});

// ── OpportunityController — real server-side stage validation ──────────────────

test('an opportunity cannot be created or updated with a stage outside its pipeline real stages', function () {
    $user = deepAuditUser('STGA');
    $pipeline = Pipeline::factory()->create(['stages' => ['lead', 'qualified', 'won', 'lost']]);
    $token = $user->createToken('t')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/crm/opportunities', [
            'name' => 'Deal',
            'pipeline_id' => $pipeline->id,
            'stage' => 'totally-bogus-stage',
            'amount' => 1000,
        ])
        ->assertUnprocessable();

    $opp = Opportunity::factory()->create(['pipeline_id' => $pipeline->id, 'tenant_id' => $user->company_id, 'stage' => 'lead']);

    $this->withToken($token)
        ->putJson("/api/v1/crm/opportunities/{$opp->id}", ['stage' => 'another-bogus-stage'])
        ->assertUnprocessable();

    // A real stage is still accepted.
    $this->withToken($token)
        ->putJson("/api/v1/crm/opportunities/{$opp->id}", ['stage' => 'qualified'])
        ->assertOk()
        ->assertJsonPath('stage', 'qualified');
});

// ── DuplicateDetectionService — real, cheap consumer wired for the first time ──

test('the contact duplicates endpoint is reachable and scoped to the caller own company', function () {
    $user = deepAuditUser('DUPA');
    $contact = Contact::factory()->create(['company_id' => $user->company_id]);
    $token = $user->createToken('t')->plainTextToken;

    // AI embeddings are not configured in this test environment — the service's own
    // try/catch degrades to an empty array, matching this app's fallback-first design; the
    // point of this test is that the endpoint is real, routed, authorized, and scoped, not
    // that a live embeddings call succeeds.
    $this->withToken($token)
        ->getJson("/api/v1/crm/contacts/{$contact->id}/duplicates")
        ->assertOk()
        ->assertJson([]);
});

test('the contact duplicates endpoint is denied across companies', function () {
    $userA = deepAuditUser('DUPB');
    $userB = deepAuditUser('DUPC');

    $contactB = Contact::factory()->create(['company_id' => $userB->company_id]);
    $tokenA = $userA->createToken('t')->plainTextToken;

    $this->withToken($tokenA)
        ->getJson("/api/v1/crm/contacts/{$contactB->id}/duplicates")
        ->assertForbidden();
});

// ── Re-verification: previously-fixed core entities still hold ─────────────────

test('re-verification: contacts, accounts, leads, opportunities, campaigns, pipelines and activities all still deny cross-company access', function () {
    $userA = deepAuditUser('REVA');
    $userB = deepAuditUser('REVB');

    $pipeline = Pipeline::factory()->create();

    $contactB = Contact::factory()->create(['company_id' => $userB->company_id]);
    $accountB = Account::factory()->create(['company_id' => $userB->company_id]);
    $leadB = Lead::factory()->create(['company_id' => $userB->company_id]);
    $oppB = Opportunity::factory()->create(['pipeline_id' => $pipeline->id, 'tenant_id' => $userB->company_id]);
    $campaignB = Campaign::factory()->create(['company_id' => $userB->company_id]);
    $pipelineB = Pipeline::factory()->create(['company_id' => $userB->company_id]);

    $tokenA = $userA->createToken('t')->plainTextToken;

    $this->withToken($tokenA)->getJson("/api/v1/crm/contacts/{$contactB->id}")->assertForbidden();
    $this->withToken($tokenA)->getJson("/api/v1/crm/accounts/{$accountB->id}")->assertForbidden();
    $this->withToken($tokenA)->getJson("/api/v1/crm/leads/{$leadB->id}")->assertForbidden();
    $this->withToken($tokenA)->getJson("/api/v1/crm/opportunities/{$oppB->id}")->assertForbidden();
    $this->withToken($tokenA)->getJson("/api/v1/crm/campaigns/{$campaignB->id}")->assertForbidden();
    $this->withToken($tokenA)->getJson("/api/v1/crm/pipelines/{$pipelineB->id}")->assertForbidden();
});

// ── Layer 13 (AI): 'CRM' was only registered with 3/9 real actions, and none of the ──
// ── module's ~13 real Vue pages ever called useAiAssistant() at all ────────────────

test('the real /api/v1/ai/assist endpoint returns real, non-empty guidance for every newly-wired CRM screen', function () {
    // Uses the shared actingAsUser() helper (not deepAuditUser()) since this test needs
    // no multi-company isolation, only the module:AI route gate satisfied — deepAuditUser()
    // deliberately only enables the 'CRM' module (its whole point being tight per-test
    // scoping), so 'AI' would 403 under it, same as any tenant with AI disabled.
    $user = actingAsUser('admin');

    foreach ([
        'view_contacts_list',
        'manage_leads',
        'manage_opportunities_kanban',
        'manage_quotes',
        'manage_territories',
        'view_sales_forecast',
    ] as $action) {
        $response = $this
            ->postJson('/api/v1/ai/assist', ['module' => 'CRM', 'action' => $action, 'locale' => 'fr'])
            ->assertOk();

        expect($response->json('what_to_do'))->not->toBeEmpty()
            ->and($response->json('how_to_do'))->not->toBeEmpty();
    }
});

test('CRM.supportedModules() lists every action a real CRM Vue page actually requests', function () {
    $modules = app(\Modules\AI\Services\AiContextualAssistantService::class)->supportedModules();

    expect($modules)->toHaveKey('CRM');
    foreach ([
        'create_contact',
        'view_dashboard',
        'create_opportunity',
        'view_contacts_list',
        'manage_leads',
        'manage_opportunities_kanban',
        'manage_quotes',
        'manage_territories',
        'view_sales_forecast',
    ] as $action) {
        expect($modules['CRM'])->toContain($action);
    }
});
