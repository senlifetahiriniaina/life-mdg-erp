<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\TaxJurisdiction;
use Tests\TestCase;

/**
 * Chantier 32.14 — 14-layer deep audit of Modules\Accounting.
 *
 * Covers:
 *  - the 10 dead-handle()/dead-constructor BaseAsyncJob-extending jobs (deleted, not fixed —
 *    confirmed zero dispatch site + confirmed inferior/fake duplicates of already-real,
 *    already-tested synchronous ConsolidationService logic).
 *  - the previously-orphaned AdvancedTaxComplianceService, now wired into a real endpoint.
 */
class Chantier32AccountingDeepAuditTest extends TestCase
{
    // ------------------------------------------------------------------
    // Priority 1: the 10 dead jobs — confirmed deleted, not merely fixed.
    // ------------------------------------------------------------------

    public function test_the_ten_dead_baseasyncjob_extending_jobs_no_longer_exist(): void
    {
        $deleted = [
            \Modules\Accounting\Jobs\ValidateComplianceJob::class,
            \Modules\Accounting\Jobs\EliminateIntercompanyJob::class,
            \Modules\Accounting\Jobs\GenerateConsolidatedReportsJob::class,
            \Modules\Accounting\Jobs\UpdateDeferredTaxJob::class,
            \Modules\Accounting\Jobs\CalculateTaxProvisionsJob::class,
            \Modules\Accounting\Jobs\CalculateMinorityInterestJob::class,
            \Modules\Accounting\Jobs\ComputeTransferPricingJob::class,
            \Modules\Accounting\Jobs\ConsolidateFinancialsJob::class,
            \Modules\Accounting\Jobs\GenerateTaxReportsJob::class,
            \Modules\Accounting\Jobs\ProcessConsolidationAdjustmentsJob::class,
        ];

        foreach ($deleted as $class) {
            $this->assertFalse(class_exists($class), "{$class} should have been deleted (confirmed dead: fatal on construct, zero dispatch site, inferior/fake duplicate of real ConsolidationService logic).");
        }
    }

    public function test_intercompany_elimination_real_logic_still_lives_in_consolidation_service_not_the_deleted_job(): void
    {
        $this->assertTrue(method_exists(\Modules\Accounting\Services\ConsolidationService::class, 'eliminateIntercompanyTransactions'));
    }

    // ------------------------------------------------------------------
    // AdvancedTaxComplianceService — real, previously-orphaned, now wired.
    // ------------------------------------------------------------------

    public function test_tax_calculate_endpoint_computes_real_vat_via_previously_orphaned_service(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $user->assignRole('accountant');
        $user->givePermissionTo('accounting.tax_compliance.view');

        $jurisdiction = TaxJurisdiction::factory()->create([
            'tax_rate' => 20,
            'tax_calculation_method' => 'exclusive',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/accounting/tax-compliance-reports/calculate', [
            'tax_jurisdiction_id' => $jurisdiction->id,
            'type' => 'vat',
            'base_amount' => 1000,
        ]);

        $response->assertOk()
            ->assertJsonPath('type', 'vat')
            ->assertJsonPath('result.tax_amount', 200)
            ->assertJsonPath('result.total_amount', 1200);
    }

    public function test_tax_calculate_endpoint_computes_real_progressive_income_tax(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $user->assignRole('accountant');
        $user->givePermissionTo('accounting.tax_compliance.view');

        $jurisdiction = TaxJurisdiction::factory()->create([
            'tax_rules' => ['tax_brackets' => [
                ['limit' => 10000, 'rate' => 10],
                ['limit' => 50000, 'rate' => 20],
            ]],
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/accounting/tax-compliance-reports/calculate', [
            'tax_jurisdiction_id' => $jurisdiction->id,
            'type' => 'income_tax',
            'gross_income' => 20000,
        ]);

        $response->assertOk()
            // 10000 * 10% + 10000 * 20% = 1000 + 2000 = 3000
            ->assertJsonPath('result.tax_amount', 3000);
    }

    public function test_tax_calculate_endpoint_rejects_zero_cost_transfer_price_to_avoid_division_by_zero(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $user->assignRole('accountant');
        $user->givePermissionTo('accounting.tax_compliance.view');
        $jurisdiction = TaxJurisdiction::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/accounting/tax-compliance-reports/calculate', [
            'tax_jurisdiction_id' => $jurisdiction->id,
            'type' => 'transfer_price',
            'cost' => 0,
        ]);

        $response->assertStatus(422);
    }

    public function test_tax_calculate_endpoint_denies_a_user_with_no_company(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(); // no company_id
        $user->assignRole('accountant');
        $user->givePermissionTo('accounting.tax_compliance.view');
        $jurisdiction = TaxJurisdiction::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/accounting/tax-compliance-reports/calculate', [
            'tax_jurisdiction_id' => $jurisdiction->id,
            'type' => 'vat',
            'base_amount' => 1000,
        ]);

        $response->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // BudgetManagementController — 23 of 24 methods had zero authorize().
    // ------------------------------------------------------------------

    public function test_budget_approve_is_now_gated_and_a_cross_company_manager_is_denied(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $ownerCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $budget = Budget::factory()->create(['company_id' => $ownerCompany->id, 'status' => 'draft']);

        $intruder = User::factory()->for($otherCompany)->create();
        $intruder->assignRole('manager');

        $response = $this->actingAs($intruder)->postJson("/api/v1/accounting/budgets/{$budget->id}/approve");
        $response->assertStatus(403);

        $owner = User::factory()->for($ownerCompany)->create();
        $owner->assignRole('manager');
        $response = $this->actingAs($owner)->postJson("/api/v1/accounting/budgets/{$budget->id}/approve");
        $response->assertOk();
    }

    public function test_budget_reject_is_now_gated(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $company = Company::factory()->create();
        $budget = Budget::factory()->create(['company_id' => $company->id, 'status' => 'submitted']);

        $intruder = User::factory()->for(Company::factory()->create())->create();
        $intruder->assignRole('manager');
        $this->actingAs($intruder)
            ->postJson("/api/v1/accounting/budgets/{$budget->id}/reject")
            ->assertStatus(403);

        $owner = User::factory()->for($company)->create();
        $owner->assignRole('manager');
        $this->actingAs($owner)
            ->postJson("/api/v1/accounting/budgets/{$budget->id}/reject", ['reason' => 'over cap'])
            ->assertOk();
        $this->assertDatabaseHas('acc_budgets', ['id' => $budget->id, 'status' => 'rejected']);
    }

    public function test_budget_variance_view_is_now_gated_cross_company(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $company = Company::factory()->create();
        $budget = Budget::factory()->create(['company_id' => $company->id]);

        $intruder = User::factory()->for(Company::factory()->create())->create();
        $intruder->assignRole('manager');

        $this->actingAs($intruder)
            ->getJson("/api/v1/accounting/budgets/{$budget->id}/variance")
            ->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Notification round-trip (Layer 11 — CORE) — invoice-approval
    // notifications benefit from the Core NotificationService double-
    // encode fix (verified end-to-end, not just trusted from the changelog).
    // ------------------------------------------------------------------

    public function test_invoice_approval_notification_round_trips_cleanly_through_the_fixed_notification_service(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $approver = User::factory()->create();
        $approver->assignRole('manager');

        app(\Modules\Core\Services\ParticipantNotificationService::class)->notifyProcess(
            collect([$approver]),
            null,
            'Facture en attente d\'approbation',
            'La facture INV-1 attend votre validation.',
            ['type' => 'warning']
        );

        $notification = $approver->notifications()->first();
        $this->assertNotNull($notification);

        // The double-encode bug (Chantier 31 fix) wrote json_encode() output
        // into a column already cast to array — corrupting it into a JSON
        // string containing more escaped JSON. Confirm the raw column is a
        // clean, single-encoded JSON object (not a string value once decoded).
        $raw = \DB::table('notifications')->where('id', $notification->id)->value('data');
        $decodedOnce = json_decode($raw, true);
        $this->assertIsArray($decodedOnce);
        $this->assertSame('Facture en attente d\'approbation', $decodedOnce['title']);

        // And the Eloquent-cast accessor gives back a real array too.
        $this->assertIsArray($notification->data);
        $this->assertSame('Facture en attente d\'approbation', $notification->data['title']);
    }

    public function test_tax_calculate_endpoint_denies_role_without_the_view_permission(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        // employee has broad wildcard permissions in this app EXCEPT accounting.tax_compliance
        // is not automatically part of that wildcard set on its own role gate — verified by the
        // route-level role: middleware, not the permission, being the actual first gate here.
        // Use a role genuinely outside the module's route gate instead.
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'sales-rep', 'guard_name' => 'web']);
        $user->assignRole('sales-rep');
        $jurisdiction = TaxJurisdiction::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/accounting/tax-compliance-reports/calculate', [
            'tax_jurisdiction_id' => $jurisdiction->id,
            'type' => 'vat',
            'base_amount' => 1000,
        ]);

        $response->assertStatus(403);
    }
}
