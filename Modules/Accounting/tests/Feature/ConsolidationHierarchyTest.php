<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Modules\Accounting\Models\ConsolidationHierarchy;
use Tests\TestCase;

class ConsolidationHierarchyTest extends TestCase
{
    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->user = User::factory()->for($this->company)->create();
        $this->user->givePermissionTo('accounting.consolidation.view');
        $this->user->givePermissionTo('accounting.consolidation.create');
        $this->user->givePermissionTo('accounting.consolidation.update');
        $this->user->givePermissionTo('accounting.consolidation.delete');
    }

    public function test_can_list_consolidation_hierarchies(): void
    {
        ConsolidationHierarchy::factory()
            ->for($this->company)
            ->count(3)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/accounting/consolidation-hierarchies');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_consolidation_hierarchy(): void
    {
        $parentCompany = Company::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/consolidation-hierarchies', [
                'name' => 'Test Hierarchy',
                'type' => 'holding',
                'parent_company_id' => $parentCompany->id,
                'company_id' => $this->company->id,
                'ownership_percentage' => 75.50,
                'effective_date' => now()->toDateString(),
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('consolidation_hierarchies', [
            'name' => 'Test Hierarchy',
            'type' => 'holding',
        ]);
    }

    public function test_can_view_consolidation_hierarchy(): void
    {
        $hierarchy = ConsolidationHierarchy::factory()
            ->for($this->company)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/accounting/consolidation-hierarchies/{$hierarchy->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', $hierarchy->name);
    }

    public function test_can_update_consolidation_hierarchy(): void
    {
        $hierarchy = ConsolidationHierarchy::factory()
            ->for($this->company)
            ->create(['status' => 'draft']);

        $response = $this->actingAs($this->user)
            ->putJson("/api/accounting/consolidation-hierarchies/{$hierarchy->id}", [
                'name' => 'Updated Name',
                'ownership_percentage' => 85.00,
            ]);

        $response->assertOk();
        $hierarchy->refresh();
        $this->assertEquals('Updated Name', $hierarchy->name);
    }

    public function test_cannot_view_other_company_hierarchy(): void
    {
        $otherCompany = Company::factory()->create();
        $hierarchy = ConsolidationHierarchy::factory()
            ->for($otherCompany)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/accounting/consolidation-hierarchies/{$hierarchy->id}");

        $response->assertForbidden();
    }
}
