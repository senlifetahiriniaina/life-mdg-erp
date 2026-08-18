<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Projects\Models\BudgetLine;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectRisk;

/**
 * Chantier 8.4 (Projects): the Phase 49 route group (budget/kpis/risks/
 * portfolio*) had no module:/role: gating at all — any authenticated user
 * of any tenant could read any project's budget/KPI/risk data. Its
 * prj_budget_lines/prj_expense_ledger/prj_risks tables also had no
 * migration — every real call fatalled with "table not found" before the
 * RBAC gap even mattered in practice.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function projectBudgetTestUser(string $role): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('an employee can read a project budget summary with real data', function () {
    $user = projectBudgetTestUser('employee');
    $project = Project::factory()->create();
    BudgetLine::factory()->create([
        'project_id' => $project->id,
        'category' => 'capex',
        'estimated_amount' => 1_000_000,
        'actual_amount' => 400_000,
    ]);

    $response = test()->actingAs($user, 'sanctum')->getJson("/api/v1/projects/{$project->id}/budget");

    $response->assertOk();
    expect($response->json('data.summary.total_budget_xof'))->toBeGreaterThanOrEqual(1_000_000);
});

test('an employee can read a project risk register with real data', function () {
    $user = projectBudgetTestUser('employee');
    $project = Project::factory()->create();
    ProjectRisk::factory()->create(['project_id' => $project->id, 'status' => 'open']);

    $response = test()->actingAs($user, 'sanctum')->getJson("/api/v1/projects/{$project->id}/risks");

    $response->assertOk();
});

test('an unauthenticated user cannot read project budget/kpi/risk data', function () {
    $project = Project::factory()->create();

    test()->getJson("/api/v1/projects/{$project->id}/budget")->assertUnauthorized();
    test()->getJson("/api/v1/projects/{$project->id}/kpis")->assertUnauthorized();
    test()->getJson("/api/v1/projects/{$project->id}/risks")->assertUnauthorized();
});

test('a user with no Projects module access cannot read project budget data', function () {
    $user = projectBudgetTestUser('sales-rep');
    $project = Project::factory()->create();

    test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/budget")
        ->assertForbidden();
});
