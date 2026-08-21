<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Sales\Models\SalesObjective;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Services\SalesService;
use Spatie\Permission\Models\Permission;

/**
 * Chantier 26 (volet B de la feuille de route Chantier 26) — objectifs
 * commerciaux assistés par IA. L'application propose 3 cibles de chiffre
 * d'affaires (jamais inventées — dérivées de l'historique réel des
 * commandes confirmées), modifiables puis validables sur l'interface ; la
 * validation d'une proposition rejette automatiquement ses sœurs.
 */
function objectiveUser(string $role = 'sales-manager'): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $company = Company::create([
        'name'     => 'Chantier26 Objectives Co '.uniqid(),
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);
    test()->actingAs($user, 'sanctum');

    return $user;
}

/** Creates N confirmed sales orders, one per given "days ago", each with the given total. */
function seedConfirmedOrders(User $user, array $daysAgoAmounts, ?int $salesRepId = null, ?int $contactId = null, ?int $productId = null): void
{
    $service = app(SalesService::class);

    foreach ($daysAgoAmounts as $daysAgo => $amount) {
        $lines = $productId
            ? [['product_id' => $productId, 'description' => 'test', 'quantity' => 1, 'unit_price' => $amount]]
            : [['description' => 'test', 'quantity' => 1, 'unit_price' => $amount]];

        $order = $service->createOrder([
            'tenant_id'    => $user->company_id,
            'created_by'   => $user->id,
            'sales_rep_id' => $salesRepId ?? $user->id,
            'contact_id'   => $contactId,
            'currency'     => 'MGA',
            'lines'        => $lines,
        ]);

        $order->update(['status' => 'confirmed', 'confirmed_at' => now()->subDays($daysAgo)]);
    }
}

describe('SalesObjectiveService — real historical data', function () {
    test('proposes 3 candidates grounded in the real 6-month average, never invented numbers', function () {
        $user = objectiveUser();
        seedConfirmedOrders($user, [90 => 100000, 60 => 100000, 30 => 100000]);

        $response = $this->postJson('/api/v1/sales/objectives/propose', [
            'scope'        => 'global',
            'period_start' => now()->addMonth()->startOfMonth()->toDateString(),
            'period_end'   => now()->addMonth()->endOfMonth()->toDateString(),
        ]);

        $response->assertCreated();
        $data = $response->json('data');
        expect($data)->toHaveCount(3);

        $labels = collect($data)->pluck('proposal_label');
        expect($labels->all())->toBe(['Conservateur', 'Modéré', 'Ambitieux']);

        // 100,000/month average, 1-month target period: 100000/105000/115000.
        expect((float) $data[0]['target_amount'])->toBe(100000.0)
            ->and((float) $data[1]['target_amount'])->toBe(105000.0)
            ->and((float) $data[2]['target_amount'])->toBe(115000.0)
            ->and($data[0]['status'])->toBe('proposed')
            ->and($data[0]['basis'])->not->toBeEmpty();
    });

    test('validating one proposal rejects its siblings, and applies user overrides', function () {
        $user = objectiveUser();
        seedConfirmedOrders($user, [60 => 200000, 30 => 200000]);

        $propose = $this->postJson('/api/v1/sales/objectives/propose', [
            'scope'        => 'global',
            'period_start' => now()->addMonth()->startOfMonth()->toDateString(),
            'period_end'   => now()->addMonth()->endOfMonth()->toDateString(),
        ])->json('data');

        $chosen = $propose[1]; // "Modéré"

        $response = $this->postJson("/api/v1/sales/objectives/{$chosen['id']}/validate", [
            'target_amount' => 250000,
        ]);

        $response->assertOk();
        expect($response->json('status'))->toBe('validated')
            ->and((float) $response->json('target_amount'))->toBe(250000.0);

        $siblingStatuses = SalesObjective::where('id', '!=', $chosen['id'])->pluck('status')->unique();
        expect($siblingStatuses->all())->toBe(['rejected']);
    });

    test('scope=rep filters strictly by sales_rep_id, not just any order in the tenant', function () {
        $user  = objectiveUser();
        $repA  = User::factory()->create(['company_id' => $user->company_id]);
        $repB  = User::factory()->create(['company_id' => $user->company_id]);

        seedConfirmedOrders($user, [30 => 300000], $repA->id);
        seedConfirmedOrders($user, [30 => 999999], $repB->id);

        $response = $this->postJson('/api/v1/sales/objectives/propose', [
            'scope'        => 'rep',
            'scope_ref_id' => $repA->id,
            'period_start' => now()->addMonth()->startOfMonth()->toDateString(),
            'period_end'   => now()->addMonth()->endOfMonth()->toDateString(),
        ]);

        $response->assertCreated();
        // Conservateur (0% growth) === the real average for repA alone (300,000), not repB's.
        expect((float) $response->json('data.0.target_amount'))->toBe(300000.0);
    });

    test('scope=category attributes revenue at the line level via product->category', function () {
        $user = objectiveUser();
        $categoryA = \Modules\Inventory\Models\Category::factory()->create();
        $categoryB = \Modules\Inventory\Models\Category::factory()->create();
        $productA  = \Modules\Inventory\Models\Product::factory()->create(['category_id' => $categoryA->id]);
        $productB  = \Modules\Inventory\Models\Product::factory()->create(['category_id' => $categoryB->id]);

        seedConfirmedOrders($user, [30 => 400000], null, null, $productA->id);
        seedConfirmedOrders($user, [30 => 999999], null, null, $productB->id);

        $response = $this->postJson('/api/v1/sales/objectives/propose', [
            'scope'        => 'category',
            'scope_ref_id' => $categoryA->id,
            'period_start' => now()->addMonth()->startOfMonth()->toDateString(),
            'period_end'   => now()->addMonth()->endOfMonth()->toDateString(),
        ]);

        $response->assertCreated();
        expect((float) $response->json('data.0.target_amount'))->toBe(400000.0);
    });

    test('a role with no sales permission is denied', function () {
        objectiveUser('warehouse-operator');

        $this->postJson('/api/v1/sales/objectives/propose', [
            'scope'        => 'global',
            'period_start' => now()->toDateString(),
            'period_end'   => now()->addMonth()->toDateString(),
        ])->assertStatus(403);
    });

    test('a still-proposed objective can be edited and deleted, a validated one cannot', function () {
        $user = objectiveUser();
        seedConfirmedOrders($user, [30 => 100000]);

        $data = $this->postJson('/api/v1/sales/objectives/propose', [
            'scope'        => 'global',
            'period_start' => now()->addMonth()->startOfMonth()->toDateString(),
            'period_end'   => now()->addMonth()->endOfMonth()->toDateString(),
        ])->json('data');

        $proposed = $data[0];

        $this->putJson("/api/v1/sales/objectives/{$proposed['id']}", ['target_amount' => 123456])
            ->assertOk()
            ->assertJsonPath('target_amount', '123456.00');

        $this->postJson("/api/v1/sales/objectives/{$proposed['id']}/validate")->assertOk();

        // Now validated — editing must be rejected.
        $this->putJson("/api/v1/sales/objectives/{$proposed['id']}", ['target_amount' => 1])
            ->assertStatus(422);
    });

    test('web page is reachable for an authorized role', function () {
        objectiveUser();

        $response = $this->get('/sales/objectives');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Sales/Objectives/Index', false));
    });
});
