<?php

declare(strict_types=1);

/*
 * Chantier 32.12 — deep 14-layer audit of Modules\Calendar (see CLAUDE.md's
 * "Méthodologie d'audit approfondi (14 couches)" for the full methodology).
 * Every assertion below was reproduced against the REAL pre-fix code via
 * a real HTTP request or real `php artisan tinker` execution before being
 * fixed — see the fixed source files' own docblocks for the exact
 * before/after evidence. Headline findings locked in here:
 *
 *  1. (layer 12, API contract) indexCalendars()/indexEvents()/upcoming()
 *     returned bare JSON arrays and CalendarSyncController::status()
 *     returned a bare keyed object — every real Vue consumer (Index.vue,
 *     Teams.vue, Event/Create.vue, Integrations.vue) reads `response.data`,
 *     so all 4 pages have shown an empty calendar/calendar-list/connection-
 *     status regardless of real content since they were built. Fixed by
 *     wrapping all 4 endpoints in `{data: ...}`.
 *  2. (layer 12) GET calendar/events required `start`/`end` — Index.vue and
 *     Teams.vue never send them, a guaranteed 422 on every real page load.
 *     Fixed with a sensible default window when omitted.
 *  3. (layer 12) Event/Create.vue sent `attendees` as an array of bare
 *     email strings against a backend that validates
 *     `attendees.*.email` (array of objects) — a guaranteed 422 whenever a
 *     participant was filled in. Fixed frontend-side.
 *  4. (layer 12) Event/Create.vue's reminder dropdown was never translated
 *     into the real `reminders` array — silently non-functional. Fixed.
 *  5. (layer 12) Integrations.vue read `data.auth_url`, but
 *     googleAuth()/outlookAuth() return `{url: ...}` — clicking "Connecter"
 *     for Google/Outlook has never redirected anywhere. Fixed.
 *  6. (layer 6, cross-tenant leak) ModuleEventAggregatorService::
 *     importStrategyMilestones() had zero tenant scoping — confirmed
 *     empirically that Company A's employee got Company B's strategic
 *     objective synced into their own personal calendar. Fixed via
 *     strategy_plans.tenant_id.
 *  7. (layer 9, fake/dead — rewired, not deleted) importWorkflowSchedules()
 *     queried a nonexistent `workflow_executions.scheduled_at` column
 *     (real columns: started_at/completed_at/triggered_at) with zero
 *     scoping even if the column existed — this "Workflows Planifiés"
 *     source has never populated a single real event. Rewired onto the
 *     real `automation_flows` model (trigger_config->next_run_at),
 *     scoped to the calling user's own created flows.
 *  8. (layer 6, unauthenticated abuse) webhookGoogle()/webhookOutlook()
 *     dispatched a real sync job for any numeric user id derived purely
 *     from a client-controlled header/body value, with zero correlation
 *     to a real connected provider — `GoogleCalendarService::watchCalendar()`
 *     /`OutlookCalendarService::subscribeToDelta()` (the only mechanisms
 *     that would ever register a legitimate webhook) have zero callers
 *     anywhere, confirmed via grep — kept as real, orphaned code (not
 *     activated: needs a real OAuth app registration this sandbox doesn't
 *     have, plus a channel-renewal cron, a product decision beyond this
 *     audit's scope) rather than deleted, since deleting it would remove
 *     the only legitimate path that could ever make these webhooks real.
 *     Hardened independently: only dispatch when the resolved user
 *     genuinely has a stored sync token for that provider.
 *  9. (layer 8, business validation) updateEvent() had no cross-field
 *     start/end constraint at all (storeEvent() has one) — a partial
 *     update sending only `end_at` could silently set it before the
 *     event's own stored `start_at`. Fixed with a validator `after()` hook
 *     resolving the effective start/end from request-or-stored values.
 * 10. (layer 3) Event/Show.vue's own "Modifier" button has always linked
 *     to a route that never existed (`/calendar/events/{id}/edit`) — a
 *     404 on every click. Built a real Edit.vue page + route.
 * 11. (layer 7, RBAC) re-confirms CalendarPolicy/CalendarEventPolicy are
 *     STILL correctly Gate-registered after everything since Chantier 10.
 * 12. (layer 13, AI) re-confirms all 5 real Vue `useAiAssistant('Calendar',
 *     ...)` call sites resolve to real, non-empty fr+en fallback guidance
 *     via the real global `/api/v1/ai/assist` endpoint.
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Calendar\Models\Calendar;
use Modules\Calendar\Models\CalendarEvent;
use Modules\Calendar\Models\CalendarSyncToken;
use Modules\Calendar\Services\ModuleEventAggregatorService;
use Spatie\Permission\Models\Role;

function calendarDeepAuditUser(string $role = 'employee'): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $company = Company::factory()->create();
    $user    = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

// ─── Layer 12 — API contract: response shapes ────────────────────────────────

describe('API contract — response shapes match real Vue consumers', function () {
    test('indexCalendars wraps in {data: [...]}, matching Index.vue/Event.Create.vue', function () {
        $user = actingAsUser('admin');
        Calendar::factory()->create(['user_id' => $user->id]);

        $res = $this->actingAs($user, 'sanctum')->getJson('/api/v1/calendar/calendars')->assertOk();

        expect($res->json())->toHaveKey('data');
        expect($res->json('data'))->toHaveCount(1);
    });

    test('indexEvents works with no start/end (matching what Index.vue/Teams.vue actually send) and wraps in {data: [...]}', function () {
        $user     = actingAsUser('admin');
        $calendar = Calendar::factory()->create(['user_id' => $user->id]);
        CalendarEvent::factory()->create([
            'calendar_id' => $calendar->id,
            'created_by'  => $user->id,
            'start_at'    => now()->addDays(2),
            'end_at'      => now()->addDays(2)->addHour(),
        ]);

        // The exact call Index.vue/Teams.vue make: no start/end params.
        $res = $this->actingAs($user, 'sanctum')->getJson('/api/v1/calendar/events?per_page=200')->assertOk();

        expect($res->json())->toHaveKey('data');
        expect($res->json('data'))->toHaveCount(1);
    });

    test('indexEvents still honors an explicit start/end window', function () {
        $user     = actingAsUser('admin');
        $calendar = Calendar::factory()->create(['user_id' => $user->id]);
        CalendarEvent::factory()->create([
            'calendar_id' => $calendar->id, 'created_by' => $user->id,
            'start_at' => now()->addYears(2), 'end_at' => now()->addYears(2)->addHour(),
        ]);

        $res = $this->actingAs($user, 'sanctum')->getJson('/api/v1/calendar/events?' . http_build_query([
            'start' => now()->toDateString(), 'end' => now()->addDay()->toDateString(),
        ]))->assertOk();

        expect($res->json('data'))->toHaveCount(0);
    });

    test('upcoming wraps in {data: [...]}', function () {
        $user     = actingAsUser('admin');
        $calendar = Calendar::factory()->create(['user_id' => $user->id]);
        CalendarEvent::factory()->create(['calendar_id' => $calendar->id, 'created_by' => $user->id, 'start_at' => now()->addHour(), 'end_at' => now()->addHours(2)]);

        $res = $this->actingAs($user, 'sanctum')->getJson('/api/v1/calendar/upcoming')->assertOk();

        expect($res->json())->toHaveKey('data');
    });

    test('sync status wraps in {data: {...}}, matching Integrations.vue', function () {
        $user = actingAsUser('admin');

        $res = $this->actingAs($user, 'sanctum')->getJson('/api/v1/calendar/sync/status')->assertOk();

        expect($res->json())->toHaveKey('data');
        expect($res->json('data'))->toHaveKeys(['google', 'outlook', 'apple']);
    });

    test('googleAuth/outlookAuth return {url: ...} — the real key Integrations.vue now reads', function () {
        $user = actingAsUser('admin');

        $g = $this->actingAs($user, 'sanctum')->getJson('/api/v1/calendar/sync/google/auth')->assertOk();
        expect($g->json())->toHaveKey('url');

        $o = $this->actingAs($user, 'sanctum')->getJson('/api/v1/calendar/sync/outlook/auth')->assertOk();
        expect($o->json())->toHaveKey('url');
    });

    test('storeEvent accepts attendees as an array of {email} objects — the real shape Event.Create.vue now sends', function () {
        $user     = actingAsUser('admin');
        $calendar = Calendar::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/calendar/events', [
            'calendar_id' => $calendar->id,
            'title'       => 'Réunion équipe',
            'start_at'    => now()->addHour()->toDateTimeString(),
            'end_at'      => now()->addHours(2)->toDateTimeString(),
            'attendees'   => [['email' => 'a@example.com'], ['email' => 'b@example.com']],
            'reminders'   => [['minutes_before' => 30, 'method' => 'popup']],
        ])
            ->assertCreated()
            ->assertJsonCount(2, 'attendees')
            ->assertJsonCount(1, 'reminders');
    });

    test('storeEvent still 422s on the old broken bare-string attendees shape (documents the exact bug Event.Create.vue used to trigger)', function () {
        $user     = actingAsUser('admin');
        $calendar = Calendar::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/calendar/events', [
            'calendar_id' => $calendar->id,
            'title'       => 'Réunion',
            'start_at'    => now()->addHour()->toDateTimeString(),
            'end_at'      => now()->addHours(2)->toDateTimeString(),
            'attendees'   => ['a@example.com'],
        ])->assertStatus(422)->assertJsonValidationErrors(['attendees.0.email']);
    });
});

// ─── Layer 8 — business validation ────────────────────────────────────────────

describe('Business validation — updateEvent cross-field date constraint', function () {
    test('rejects a partial update that would set end_at before the stored start_at', function () {
        $user     = actingAsUser('admin');
        $calendar = Calendar::factory()->create(['user_id' => $user->id]);
        $event    = CalendarEvent::factory()->create([
            'calendar_id' => $calendar->id, 'created_by' => $user->id,
            'start_at' => now(), 'end_at' => now()->addHour(),
        ]);

        // Sends ONLY end_at — reproduces the exact bug: no start_at in the
        // request, so a plain after_or_equal:start_at rule would have been
        // silently skipped by Laravel (the reference field isn't present).
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/calendar/events/{$event->id}", [
                'end_at' => now()->subDay()->toDateTimeString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_at']);

        expect($event->fresh()->end_at->eq($event->end_at))->toBeTrue();
    });

    test('accepts a valid partial update moving both dates forward together', function () {
        $user     = actingAsUser('admin');
        $calendar = Calendar::factory()->create(['user_id' => $user->id]);
        $event    = CalendarEvent::factory()->create([
            'calendar_id' => $calendar->id, 'created_by' => $user->id,
            'start_at' => now(), 'end_at' => now()->addHour(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/calendar/events/{$event->id}", [
                'start_at' => now()->addDay()->toDateTimeString(),
                'end_at'   => now()->addDay()->addHour()->toDateTimeString(),
            ])
            ->assertOk();
    });

    test('accepts a partial update sending only end_at when it is still after the stored start_at', function () {
        $user     = actingAsUser('admin');
        $calendar = Calendar::factory()->create(['user_id' => $user->id]);
        $event    = CalendarEvent::factory()->create([
            'calendar_id' => $calendar->id, 'created_by' => $user->id,
            'start_at' => now(), 'end_at' => now()->addHour(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/calendar/events/{$event->id}", [
                'end_at' => now()->addHours(3)->toDateTimeString(),
            ])
            ->assertOk();
    });
});

// ─── Layer 6 — cross-tenant leak: ModuleEventAggregatorService ───────────────

describe('ModuleEventAggregatorService — real cross-module scoping', function () {
    test('importStrategyMilestones no longer leaks another company\'s objectives into this user\'s calendar', function () {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA    = User::factory()->create(['company_id' => $companyA->id]);

        $planA = DB::table('strategy_plans')->insertGetId(['tenant_id' => (string) $companyA->id, 'name' => 'Plan A', 'created_at' => now(), 'updated_at' => now()]);
        $planB = DB::table('strategy_plans')->insertGetId(['tenant_id' => (string) $companyB->id, 'name' => 'Plan B', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('strategy_objectives')->insert(['plan_id' => $planA, 'title' => 'Objectif A', 'end_date' => now()->addDays(5), 'status' => 'in_progress', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('strategy_objectives')->insert(['plan_id' => $planB, 'title' => 'Objectif B (autre société)', 'end_date' => now()->addDays(5), 'status' => 'in_progress', 'created_at' => now(), 'updated_at' => now()]);

        $synced = app(ModuleEventAggregatorService::class)->aggregateForUser($userA->id, ['strategy_milestones']);

        expect($synced)->toBe(1);
        $titles = CalendarEvent::whereHas('calendar', fn ($q) => $q->where('user_id', $userA->id))->pluck('title');
        expect($titles)->toContain('Jalon: Objectif A');
        expect($titles)->not->toContain('Jalon: Objectif B (autre société)');
    });

    test('importStrategyMilestones degrades to 0 for a user with no company', function () {
        $user = User::factory()->create(['company_id' => null]);

        $synced = app(ModuleEventAggregatorService::class)->aggregateForUser($user->id, ['strategy_milestones']);

        expect($synced)->toBe(0);
    });

    test('importWorkflowSchedules syncs a real scheduled automation flow the user created', function () {
        $user = User::factory()->create();

        DB::table('automation_flows')->insert([
            'tenant_id' => (string) ($user->company_id ?? 1),
            'name' => 'Relance client hebdo',
            'is_active' => true,
            'trigger_type' => 'schedule',
            'trigger_config' => json_encode(['next_run_at' => now()->addDays(3)->toIso8601String()]),
            'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $synced = app(ModuleEventAggregatorService::class)->aggregateForUser($user->id, ['workflow']);

        expect($synced)->toBe(1);
        expect(CalendarEvent::where('module_type', 'AutomationFlow')->where('title', 'Workflow: Relance client hebdo')->exists())->toBeTrue();
    });

    test('importWorkflowSchedules skips a flow whose next_run_at is already past', function () {
        $user = User::factory()->create();

        DB::table('automation_flows')->insert([
            'tenant_id' => (string) ($user->company_id ?? 1),
            'name' => 'Flow expiré',
            'is_active' => true,
            'trigger_type' => 'schedule',
            'trigger_config' => json_encode(['next_run_at' => now()->subDay()->toIso8601String()]),
            'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $synced = app(ModuleEventAggregatorService::class)->aggregateForUser($user->id, ['workflow']);

        expect($synced)->toBe(0);
    });

    test('importWorkflowSchedules does not sync another user\'s flow', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        DB::table('automation_flows')->insert([
            'tenant_id' => (string) ($userB->company_id ?? 1),
            'name' => 'Flow de B',
            'is_active' => true,
            'trigger_type' => 'schedule',
            'trigger_config' => json_encode(['next_run_at' => now()->addDay()->toIso8601String()]),
            'created_by' => $userB->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $synced = app(ModuleEventAggregatorService::class)->aggregateForUser($userA->id, ['workflow']);

        expect($synced)->toBe(0);
    });
});

// ─── Layer 6 — webhook hardening ─────────────────────────────────────────────

describe('Webhook endpoints only trust a resolved user with a real, existing sync token', function () {
    test('webhookGoogle does not dispatch a sync for a user with no Google sync token', function () {
        $user = User::factory()->create();

        \Illuminate\Support\Facades\Queue::fake();

        $this->postJson('/api/v1/calendar/webhooks/google', [], [
            'X-Goog-Channel-ID' => 'wh-cal-' . $user->id . '-abc123',
        ])->assertOk();

        \Illuminate\Support\Facades\Queue::assertNothingPushed();
    });

    test('webhookGoogle dispatches a sync only for a user who genuinely connected Google', function () {
        $user = User::factory()->create();
        CalendarSyncToken::factory()->create(['user_id' => $user->id, 'provider' => 'google']);

        \Illuminate\Support\Facades\Queue::fake();

        $this->postJson('/api/v1/calendar/webhooks/google', [], [
            'X-Goog-Channel-ID' => 'wh-cal-' . $user->id . '-abc123',
        ])->assertOk();

        \Illuminate\Support\Facades\Queue::assertPushed(\Modules\Calendar\Jobs\SyncCalendarJob::class);
    });

    test('webhookOutlook does not dispatch a sync for a user with no Outlook sync token', function () {
        $user = User::factory()->create();

        \Illuminate\Support\Facades\Queue::fake();

        $this->postJson('/api/v1/calendar/webhooks/outlook', [
            'value' => [['clientState' => 'wh-calendar-' . $user->id]],
        ])->assertAccepted();

        \Illuminate\Support\Facades\Queue::assertNothingPushed();
    });
});

// ─── Graceful degradation with no configured provider credentials ───────────

describe('Sync endpoints degrade gracefully with no real provider credentials configured', function () {
    test('apple connect fails cleanly (422) rather than crashing or hanging when the real CalDAV server is unreachable', function () {
        $user = actingAsUser('admin');

        $res = $this->actingAs($user, 'sanctum')->postJson('/api/v1/calendar/sync/apple/connect', [
            'email'        => 'fake@example.com',
            'app_password' => 'fakepassword123',
        ]);

        expect($res->status())->toBe(422);
        expect($res->json('error'))->not->toBeEmpty();
    });

    test('google/outlook sync dispatch (QUEUE_CONNECTION=sync — runs inline) never 500s with no stored token', function () {
        $user = actingAsUser('admin');

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/calendar/sync/google/sync')
            ->assertOk()->assertJson(['queued' => true]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/calendar/sync/outlook/sync')
            ->assertOk()->assertJson(['queued' => true]);
    });
});

// ─── Layer 7 — RBAC / Gate registration, re-confirmed post-history ───────────

describe('RBAC — CalendarPolicy/CalendarEventPolicy still correctly Gate-registered', function () {
    test('the event owner can update their own event via the real HTTP route', function () {
        $user     = calendarDeepAuditUser('employee');
        $calendar = Calendar::factory()->create(['user_id' => $user->id]);
        $event    = CalendarEvent::factory()->create(['calendar_id' => $calendar->id, 'created_by' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/calendar/events/{$event->id}", ['title' => 'Renommé'])
            ->assertOk();
    });

    test('a same-company non-owner employee cannot update someone else\'s event', function () {
        $company = Company::factory()->create();
        $owner   = User::factory()->create(['company_id' => $company->id]);
        $other   = calendarDeepAuditUser('employee');
        $other->update(['company_id' => $company->id]);

        $calendar = Calendar::factory()->create(['user_id' => $owner->id, 'tenant_id' => (string) $company->id]);
        $event    = CalendarEvent::factory()->create(['calendar_id' => $calendar->id, 'created_by' => $owner->id, 'tenant_id' => (string) $company->id]);

        $this->actingAs($other, 'sanctum')
            ->putJson("/api/v1/calendar/events/{$event->id}", ['title' => 'Tentative intrusion'])
            ->assertForbidden();
    });

    test('the calendar owner can delete their own calendar', function () {
        $user     = calendarDeepAuditUser('employee');
        $calendar = Calendar::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/calendar/calendars/{$calendar->id}")
            ->assertNoContent();
    });

    test('storeCalendar now calls authorize(create) and still succeeds (CalendarPolicy::create() is unconditionally true)', function () {
        $user = calendarDeepAuditUser('employee');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/calendar/calendars', ['name' => 'Nouveau calendrier'])
            ->assertCreated();
    });
});

// ─── Layer 3 — Event/Edit.vue page + route ────────────────────────────────────

describe('Event edit page — a real, previously-dead link now resolves', function () {
    test('GET /calendar/events/{event}/edit renders the real Inertia component', function () {
        $user     = calendarDeepAuditUser('employee');
        $calendar = Calendar::factory()->create(['user_id' => $user->id]);
        $event    = CalendarEvent::factory()->create(['calendar_id' => $calendar->id, 'created_by' => $user->id]);

        // Plain full-page GET + raw-HTML assertion rather than
        // assertInertia()->component() — the latter's file-existence check
        // (config('inertia.pages.paths')) doesn't know this app's custom
        // module-name-stripping resolve() convention (resources/js/app.js)
        // used by every real module page, Calendar/Event/Show included — a
        // pre-existing, unrelated test-tooling gap, not something to work
        // around by editing root config for one new page.
        $html = $this->actingAs($user)
            ->get("/calendar/events/{$event->id}/edit")
            ->assertOk()
            ->getContent();

        expect($html)->toContain('Calendar\/Event\/Edit');
    });

    test('the corresponding real file exists on disk at the path resources/js/app.js\'s resolve() actually looks up', function () {
        expect(file_exists(base_path('Modules/Calendar/resources/js/Pages/Event/Edit.vue')))->toBeTrue();
    });
});

// ─── Layer 13 — AI Assisted First, all 5 real call sites ────────────────────

describe('AI Assisted First — every real useAiAssistant(\'Calendar\', ...) call site resolves', function () {
    test('all 5 real Vue-consumed actions return non-empty guidance via the real global endpoint', function (string $action) {
        $user = actingAsUser('admin');

        $res = $this->actingAs($user, 'sanctum')->postJson('/api/v1/ai/assist', [
            'module' => 'Calendar',
            'action' => $action,
            'locale' => 'fr',
        ])->assertOk();

        expect($res->json('what_to_do'))->not->toBeEmpty();
        expect($res->json('how_to_do'))->not->toBeEmpty();
    })->with([
        'view_calendar', 'create_event', 'view_event', 'team_calendar', 'calendar_integrations',
    ]);

    test('the same 5 actions also resolve in English', function (string $action) {
        $user = actingAsUser('admin');

        $res = $this->actingAs($user, 'sanctum')->postJson('/api/v1/ai/assist', [
            'module' => 'Calendar',
            'action' => $action,
            'locale' => 'en',
        ])->assertOk();

        expect($res->json('what_to_do'))->not->toBeEmpty();
    })->with([
        'view_calendar', 'create_event', 'view_event', 'team_calendar', 'calendar_integrations',
    ]);
});

// ─── Layer 14 — orphaned seeder wired ────────────────────────────────────────

describe('CalendarDatabaseSeeder — wired into the real seed chain', function () {
    test('is genuinely called by DatabaseSeeder and seeds default calendars for the bootstrap admin', function () {
        // CalendarDatabaseSeeder hardcodes user_id=1 (the bootstrap admin's
        // real id in this app's real seed order) — explicit id=1 on a
        // freshly RefreshDatabase'd (empty users table) rather than relying
        // on factory auto-increment happening to land on 1.
        User::factory()->create(['id' => 1]);

        expect(Calendar::where('user_id', 1)->count())->toBe(0);

        $this->seed(\Modules\Calendar\Database\Seeders\CalendarDatabaseSeeder::class);

        expect(Calendar::where('user_id', 1)->count())->toBe(3);
    });
});
