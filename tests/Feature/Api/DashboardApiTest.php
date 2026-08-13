<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Auth guard ─────────────────────────────────────────────────────────────

test('unauthenticated requests to dashboard metrics are rejected', function () {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
});

test('unauthenticated requests to ai insights are rejected', function () {
    $this->getJson('/api/v1/dashboard/ai-insights')->assertUnauthorized();
});

// ── Metrics by role ────────────────────────────────────────────────────────

it('can load admin dashboard metrics', function () {
    $user = actingAsUser('admin');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'role',
            'users_count',
            'modules_enabled',
            'total_revenue',
            'open_tickets',
            'pending_approvals',
            'active_users_today',
            'mrr',
            'db_latency_ms',
        ])
        ->assertJsonPath('role', 'admin');
});

it('can load sales rep dashboard metrics', function () {
    $user = actingAsUser('sales-rep');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'role',
            'my_leads_count',
            'my_opportunities_count',
            'my_pipeline_value',
            'quota_progress',
            'open_activities',
            'won_deals_this_month',
        ])
        ->assertJsonPath('role', 'sales-rep');
});

it('can load hr manager dashboard metrics', function () {
    $user = actingAsUser('hr-manager');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'role',
            'headcount',
            'open_positions',
            'pending_leaves',
            'upcoming_reviews',
            'turnover_rate',
            'avg_salary',
        ])
        ->assertJsonPath('role', 'hr-manager');
});

it('can load accountant dashboard metrics', function () {
    $user = actingAsUser('accountant');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'role',
            'unpaid_invoices_count',
            'unpaid_invoices_total',
            'expense_reports_pending',
            'bank_reconciliation_pending',
            'vat_due_amount',
            'overdue_invoices',
        ])
        ->assertJsonPath('role', 'accountant');
});

it('can load employee dashboard metrics', function () {
    $user = actingAsUser('employee');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'role',
            'my_open_tasks',
            'my_leave_balance',
            'my_pending_expenses',
            'my_open_tickets',
            'upcoming_events',
        ])
        ->assertJsonPath('role', 'employee');
});

it('can load manager dashboard metrics', function () {
    $user = actingAsUser('manager');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'role',
            'team_size',
            'pending_leave_requests',
            'open_tasks',
            'overdue_tasks',
            'budget_consumed',
            'team_performance_avg',
        ])
        ->assertJsonPath('role', 'manager');
});

// ── AI Insights ────────────────────────────────────────────────────────────

it('can load ai insights', function () {
    $user = actingAsUser('admin');

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard/ai-insights')
        ->assertOk();

    $data = $response->json();

    expect($data)->toBeArray()
        ->and(count($data))->toBeLessThanOrEqual(5)
        ->and(count($data))->toBeGreaterThanOrEqual(1);

    foreach ($data as $insight) {
        expect($insight)->toHaveKeys(['type', 'module', 'title', 'description', 'action_label', 'action_route', 'priority']);
        expect($insight['type'])->toBeIn(['alert', 'opportunity', 'action', 'info']);
        expect($insight['priority'])->toBeInt()->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(5);
    }
});

it('ai insights are sorted by priority ascending', function () {
    $user = actingAsUser('admin');

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard/ai-insights')
        ->assertOk();

    $data = $response->json();
    $priorities = array_column($data, 'priority');
    $sorted = $priorities;
    sort($sorted);

    expect($priorities)->toBe($sorted);
});

it('ai insights for sales-rep contain crm module', function () {
    $user = actingAsUser('sales-rep');

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/dashboard/ai-insights')
        ->assertOk();

    $modules = array_column($response->json(), 'module');
    expect(in_array('CRM', $modules, true))->toBeTrue();
});
