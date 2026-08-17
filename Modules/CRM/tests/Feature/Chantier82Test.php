<?php

namespace Modules\CRM\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\Account;
use Modules\CRM\Models\Territory;
use Modules\CRM\Models\TerritoryAssignment;
use Tests\TestCase;

/**
 * Chantier 8.2: CRM cross-layer audit remediation.
 *
 * - TerritoryController::coverage() replaces the deleted TerritoryManagementController
 *   (which called TerritoryManagementService methods that didn't exist at all).
 * - The 4 previously-unrouted Web pages (Quotes/Territories/Forecast/Opportunity
 *   Scoring) now have real routes.
 * - CampaignController/WorkflowBuilderController now enforce their policies —
 *   a non-owner without the edit permission must be blocked.
 */
class Chantier82Test extends TestCase
{
    use RefreshDatabase;

    public function test_territory_coverage_reports_assigned_and_gap_territories(): void
    {
        $user = $this->actingAsUser('admin');

        $covered = Territory::factory()->create(['is_active' => true]);
        $gap = Territory::factory()->create(['is_active' => true]);
        $account = Account::factory()->create();
        TerritoryAssignment::create([
            'territory_id' => $covered->id,
            'account_id' => $account->id,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
            'auto_assigned' => false,
        ]);

        $response = $this->getJson('/api/v1/crm/territory-management/coverage');

        $response->assertOk()
            ->assertJsonStructure(['total', 'assigned', 'unassigned', 'percentage', 'gaps']);
        $gapIds = collect($response->json('gaps'))->pluck('id');
        $this->assertTrue($gapIds->contains($gap->id));
        $this->assertFalse($gapIds->contains($covered->id));
    }

    public function test_quotes_page_renders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/crm/quotes');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('CRM/Quotes/Index', false));
    }

    public function test_territories_page_renders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/crm/territories');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('CRM/Territories/Index', false));
    }

    public function test_forecast_page_renders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/crm/forecast');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('CRM/Forecast/Index', false));
    }

    public function test_opportunity_scoring_page_renders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/crm/opportunities/scoring');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('CRM/Opportunities/Scoring', false));
    }

    public function test_user_without_campaign_permission_cannot_create_campaign(): void
    {
        // Seed permissions (so crm.campaigns.create really exists) but assign no
        // role — CampaignController now enforces CampaignPolicy (previously
        // allowed any authenticated user).
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/crm/campaigns', [
            'name' => 'Unauthorized Campaign',
            'type' => 'email',
        ]);

        $response->assertForbidden();
    }

    public function test_user_without_workflow_permission_cannot_create_workflow(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/crm/workflows', [
            'name' => 'Unauthorized Workflow',
            'trigger_type' => 'manual',
        ]);

        $response->assertForbidden();
    }
}
