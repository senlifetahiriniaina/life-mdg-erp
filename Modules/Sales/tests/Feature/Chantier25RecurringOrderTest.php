<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Modules\Sales\Models\RecurringOrderTemplate;
use Modules\Sales\Models\SalesOrder;
use Spatie\Permission\Models\Permission;

/**
 * Chantier 25 (volet E de la feuille de route Chantier 21) — commandes
 * récurrentes : un modèle réutilisable (client + lignes + périodicité)
 * génère une vraie SalesOrder via le vrai SalesService::createOrder(), à
 * échéance régulière, sur la vraie route HTTP de bout en bout.
 */
function recurringOrderUser(string $suffix): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $company = Company::create([
        'name' => "Chantier25 Sales Co {$suffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('sales-manager');

    return $user;
}

test('the recurring orders web page is reachable', function () {
    $user = recurringOrderUser('A');

    test()->actingAs($user)
        ->get('/sales/recurring-orders')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Sales/RecurringOrders/Index', false));
});

test('creating a template requires at least one line and generates a real reference', function () {
    $user = recurringOrderUser('B');

    $response = test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/sales/recurring-order-templates', [
            'name' => 'Client répétitif — commande mensuelle',
            'recurrence' => 'monthly',
            'next_run_at' => now()->toDateString(),
            'lines' => [
                ['description' => 'Pantalon EPI', 'quantity' => 100, 'unit_price' => 15000],
            ],
        ])
        ->assertCreated();

    expect($response->json('data.reference'))->toStartWith('REC-');
    expect(RecurringOrderTemplate::count())->toBe(1);
    expect(RecurringOrderTemplate::first()->lines()->count())->toBe(1);

    test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/sales/recurring-order-templates', ['name' => 'Sans lignes', 'recurrence' => 'monthly', 'next_run_at' => now()->toDateString()])
        ->assertStatus(422);
});

test('runNow generates a real SalesOrder with the template lines and advances next_run_at', function () {
    $user = recurringOrderUser('C');

    $template = test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/sales/recurring-order-templates', [
            'name' => 'Client X',
            'recurrence' => 'monthly',
            'next_run_at' => now()->toDateString(),
            'lines' => [
                ['description' => 'T-shirt 250gsm', 'quantity' => 50, 'unit_price' => 8000],
            ],
        ])->json('data');

    $originalNextRun = $template['next_run_at'];

    $response = test()->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales/recurring-order-templates/{$template['id']}/run")
        ->assertCreated();

    $order = SalesOrder::find($response->json('data.id'));
    expect($order)->not->toBeNull();
    expect((float) $order->total)->toBe(400000.0); // 50 * 8000
    expect($order->lines()->count())->toBe(1);

    expect($response->json('template.next_run_at'))->not->toBe($originalNextRun);
});

test('generateDueOrders only picks up active templates whose next_run_at has passed', function () {
    $user = recurringOrderUser('D');

    // created_by is set explicitly here — a real template always carries
    // it (RecurringOrderTemplateController::store() populates it from the
    // acting user), but the factory itself leaves it unset, and
    // SalesService::createOrder() requires a non-null created_by.
    $due = RecurringOrderTemplate::factory()->create([
        'tenant_id' => $user->company_id,
        'next_run_at' => now()->subDay()->toDateString(),
        'is_active' => true,
        'created_by' => $user->id,
    ]);
    $due->lines()->create(['description' => 'Ligne due', 'quantity' => 10, 'unit_price' => 1000, 'sequence' => 0]);

    $notYetDue = RecurringOrderTemplate::factory()->create([
        'tenant_id' => $user->company_id,
        'next_run_at' => now()->addWeek()->toDateString(),
        'is_active' => true,
        'created_by' => $user->id,
    ]);
    $notYetDue->lines()->create(['description' => 'Ligne future', 'quantity' => 5, 'unit_price' => 2000, 'sequence' => 0]);

    $inactive = RecurringOrderTemplate::factory()->create([
        'tenant_id' => $user->company_id,
        'next_run_at' => now()->subDay()->toDateString(),
        'is_active' => false,
        'created_by' => $user->id,
    ]);
    $inactive->lines()->create(['description' => 'Ligne inactive', 'quantity' => 1, 'unit_price' => 1000, 'sequence' => 0]);

    $orders = app(\Modules\Sales\Services\RecurringOrderService::class)->generateDueOrders();

    expect($orders)->toHaveCount(1);
    expect($orders[0]->lines->first()->description)->toBe('Ligne due');

    // A second call must not regenerate the same (now no-longer-due) template.
    $ordersAgain = app(\Modules\Sales\Services\RecurringOrderService::class)->generateDueOrders();
    expect($ordersAgain)->toHaveCount(0);
});

test('a company cannot reach another company\'s recurring order templates', function () {
    $userA = recurringOrderUser('E1');
    $userB = recurringOrderUser('E2');

    $templateA = RecurringOrderTemplate::factory()->create(['tenant_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/sales/recurring-order-templates/{$templateA->id}")
        ->assertNotFound();
});

test('a user with no sales permission is denied', function () {
    $company = Company::create(['name' => 'No Perm Co', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
    $user = User::factory()->create(['company_id' => $company->id]);

    test()->actingAs($user, 'sanctum')
        ->getJson('/api/v1/sales/recurring-order-templates')
        ->assertForbidden();
});

test('an unauthenticated request is rejected', function () {
    test()->getJson('/api/v1/sales/recurring-order-templates')->assertUnauthorized();
});
