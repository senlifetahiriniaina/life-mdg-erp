<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Modules\Accounting\Models\RevenueContract;
use Tests\TestCase;

class RevenueContractTest extends TestCase
{
    protected User $user;
    protected Company $company;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->user = User::factory()->for($this->company)->create();
        $this->customer = Customer::factory()->for($this->company)->create();

        $this->user->givePermissionTo('accounting.revenue_recognition.view');
        $this->user->givePermissionTo('accounting.revenue_recognition.create');
        $this->user->givePermissionTo('accounting.revenue_recognition.update');
        $this->user->givePermissionTo('accounting.revenue_recognition.recognize');
    }

    public function test_can_create_revenue_contract(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/revenue-contracts', [
                'contract_number' => 'CONTRACT-001',
                'contract_type' => 'Service',
                'customer_id' => $this->customer->id,
                'contract_date' => now()->toDateString(),
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonths(12)->toDateString(),
                'contract_value' => 50000.00,
                'currency' => 'USD',
                'performance_obligation_type' => 'time_based',
                'revenue_recognition_method' => 'over_time',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('revenue_contracts', [
            'contract_number' => 'CONTRACT-001',
            'contract_value' => 50000.00,
        ]);
    }

    public function test_can_list_revenue_contracts(): void
    {
        RevenueContract::factory()
            ->for($this->customer)
            ->count(3)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/accounting/revenue-contracts');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_view_revenue_contract(): void
    {
        $contract = RevenueContract::factory()
            ->for($this->customer)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/accounting/revenue-contracts/{$contract->id}");

        $response->assertOk()
            ->assertJsonPath('data.contract_number', $contract->contract_number);
    }

    public function test_contract_number_must_be_unique(): void
    {
        RevenueContract::factory()
            ->for($this->customer)
            ->create(['contract_number' => 'CONTRACT-001']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/revenue-contracts', [
                'contract_number' => 'CONTRACT-001',
                'contract_type' => 'Service',
                'customer_id' => $this->customer->id,
                'contract_date' => now()->toDateString(),
                'start_date' => now()->toDateString(),
                'contract_value' => 50000.00,
                'currency' => 'USD',
                'performance_obligation_type' => 'time_based',
                'revenue_recognition_method' => 'over_time',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.contract_number.0', 'The contract number has already been taken.');
    }

    public function test_can_recognize_revenue(): void
    {
        $contract = RevenueContract::factory()
            ->for($this->customer)
            ->create(['status' => 'active']);

        $this->user->givePermissionTo('accounting.revenue_recognition.recognize');

        $response = $this->actingAs($this->user)
            ->postJson("/api/accounting/revenue-contracts/{$contract->id}/recognize", [
                'recognition_date' => now()->toDateString(),
                'amount' => 10000.00,
                'gl_account_id' => 1,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('revenue_recognition_schedules', [
            'revenue_contract_id' => $contract->id,
            'amount' => 10000.00,
        ]);
    }
}
