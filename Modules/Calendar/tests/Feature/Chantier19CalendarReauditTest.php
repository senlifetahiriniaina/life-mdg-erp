<?php

declare(strict_types=1);

/*
 * Chantier 19 Lot 3 — Calendar re-verification (empirical execution). See
 * CLAUDE.md's own "Chantier 19 Lot 3" entry for the full write-up. Covers:
 *
 *  1. CalendarEventPolicy::view()/viewAny() were unconditionally `true` for
 *     any authenticated user with Calendar-module access — a real,
 *     confirmed (via a real cross-company HTTP request) cross-tenant leak
 *     on `GET calendar/events/{id}` and `GET calendar/events/{id}/attendees`.
 *  2. CalendarController::addAttendee() had zero authorize() call at all.
 *  3. ModuleEventAggregatorService queried 3 nonexistent tables (`hr_leaves`,
 *     `project_tasks` with a nonexistent `assigned_to` column,
 *     `accounting_invoices` with a nonexistent `total_amount` column) and
 *     one wrong model (`strategy_kros` instead of `strategy_objectives`) —
 *     silently degraded to 0 synced events on every call.
 *  4. aggregateForUser() was confirmed to be entirely unreachable in
 *     production (no SyncCalendarJob::dispatch() call anywhere ever passes
 *     provider=null/'modules') — wired into CalendarPageController::index().
 *  5. AI-assist phantom `users.role` → real Spatie role fix.
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Calendar\Models\Calendar;
use Modules\Calendar\Models\CalendarEvent;
use Modules\Calendar\Services\ModuleEventAggregatorService;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Spatie\Permission\Models\Role;

function calendarReauditUser(string $role = 'employee'): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $company = Company::factory()->create();
    $user    = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);

    return $user;
}

it('blocks a different company from reading a private calendar event by id', function () {
    $userA  = calendarReauditUser();
    $userA2 = calendarReauditUser(); // will be reassigned into A's company below
    $userA2->update(['company_id' => $userA->company_id]);
    $userB  = calendarReauditUser();

    // Create through the real HTTP endpoint so tenant_id is populated the
    // real way (via CalendarController::storeCalendar/storeEvent), not
    // hand-crafted.
    $calendarId = $this->actingAs($userA, 'sanctum')
        ->postJson('/api/v1/calendar/calendars', ['name' => 'Perso A', 'type' => 'personal'])
        ->assertCreated()
        ->json('id');

    $eventId = $this->actingAs($userA, 'sanctum')
        ->postJson('/api/v1/calendar/events', [
            'calendar_id' => $calendarId,
            'title'       => 'Secret meeting A',
            'start_at'    => now()->addHour()->toIso8601String(),
            'end_at'      => now()->addHours(2)->toIso8601String(),
        ])->assertCreated()->json('id');

    expect((int) CalendarEvent::find($eventId)->tenant_id)->toBe((int) $userA->company_id);

    // Cross-company: denied on both the direct read and the attendee list.
    $this->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/calendar/events/{$eventId}")
        ->assertForbidden();

    $this->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/calendar/events/{$eventId}/attendees")
        ->assertForbidden();

    // addAttendee() had zero authorize() call at all before the fix.
    $this->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/calendar/events/{$eventId}/attendees", ['email' => 'intruder@example.com'])
        ->assertForbidden();

    // Same-company colleague can read it (this app's calendars are
    // intra-company shared by design, only cross-company was ever the leak).
    $this->actingAs($userA2, 'sanctum')
        ->getJson("/api/v1/calendar/events/{$eventId}")
        ->assertOk();

    // The real owner can still manage attendees normally.
    $this->actingAs($userA, 'sanctum')
        ->postJson("/api/v1/calendar/events/{$eventId}/attendees", ['email' => 'colleague@example.com'])
        ->assertCreated();
});

it('aggregates real events from multiple modules with the corrected table/column names', function () {
    $user = calendarReauditUser();

    $employee = Employee::factory()->create(['user_id' => $user->id]);
    LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'status'      => 'approved',
        'start_date'  => now()->addDays(2),
        'end_date'    => now()->addDays(3),
    ]);

    $projectId = DB::table('prj_projects')->insertGetId([
        'name' => 'Test Project', 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('prj_tasks')->insert([
        'project_id' => $projectId, 'assignee_id' => $user->id, 'title' => 'Real task',
        'status' => 'todo', 'due_date' => now()->addDays(5),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Chantier 32.12: strategy_objectives has no tenant/company column of
    // its own — tenancy is inherited via plan_id -> strategy_plans.tenant_id
    // (the same real boundary ModuleEventAggregatorService now scopes
    // through, having found and fixed a real cross-tenant leak here — see
    // that service's own docblock). A real plan is now required for the
    // objective to be synced at all, matching the corrected behavior.
    $planId = DB::table('strategy_plans')->insertGetId([
        'tenant_id' => (string) $user->company_id, 'name' => 'Plan test',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('strategy_objectives')->insert([
        'plan_id' => $planId, 'title' => 'Real objective', 'status' => 'in_progress',
        'end_date' => now()->addDays(20), 'level' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Before the fix: hr_leaves/project_tasks/strategy_kros were all wrong
    // table names (or, for strategy_kros, the wrong model shape entirely) —
    // every one of these silently synced 0 events.
    $synced = app(ModuleEventAggregatorService::class)->aggregateForUser($user->id);

    expect($synced)->toBeGreaterThanOrEqual(3);

    $types = CalendarEvent::pluck('module_type')->all();
    expect($types)->toContain('Leave');
    expect($types)->toContain('Task');
    expect($types)->toContain('StrategyObjective');
});

it('triggers module event aggregation when the Calendar page is loaded', function () {
    $user = calendarReauditUser();

    $employee = Employee::factory()->create(['user_id' => $user->id]);
    LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'status'      => 'approved',
        'start_date'  => now()->addDay(),
        'end_date'    => now()->addDays(2),
    ]);

    expect(CalendarEvent::where('module_type', 'Leave')->count())->toBe(0);

    // Before this fix, aggregateForUser() was confirmed unreachable from
    // any real HTTP path in the whole app — nothing ever dispatched
    // SyncCalendarJob with provider=null/'modules'.
    $this->actingAs($user)->get('/calendar')->assertOk();

    expect(CalendarEvent::where('module_type', 'Leave')->count())->toBe(1);
});

it('runs the Calendar AI-assist endpoint without a fatal error using the real Spatie role', function () {
    $user = calendarReauditUser('manager');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/calendar/ai/assist', [
            'action' => 'view_dashboard',
            'locale' => 'fr',
        ])
        ->assertOk()
        ->assertJsonStructure(['enabled', 'what_to_do', 'how_to_do']);
});
