<?php

declare(strict_types=1);

use Modules\Logistics\Models\Shipment;

/**
 * Chantier 10 re-verification pass: two Logistics route groups
 * (ai/assist, the ocean/air visibility aggregator) had zero module:/role:
 * gate at all — unlike every other Logistics route group and unlike
 * Inventory's own ai/assist route — so any authenticated user of any
 * module/role could reach them. Both fixed to match the main group's gate
 * (module:Logistics, role:logistics-manager,warehouse-operator,manager,admin).
 */
describe('Chantier 10 Logistics RBAC', function () {
    test('a sales-rep (zero logistics permissions) is denied the AI assist endpoint', function () {
        $user = actingAsUser('sales-rep');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/logistics/ai/assist', [
                'module' => 'Logistics',
                'action' => 'view_dashboard',
            ]);

        $response->assertStatus(403);
    });

    test('a logistics-manager can reach the AI assist endpoint', function () {
        $user = actingAsUser('logistics-manager');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/logistics/ai/assist', [
                'module' => 'Logistics',
                'action' => 'view_dashboard',
            ]);

        $response->assertStatus(200);
    });

    test('a sales-rep (zero logistics permissions) is denied shipment visibility', function () {
        $user = actingAsUser('sales-rep');
        $shipment = Shipment::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/logistics/shipments/{$shipment->id}/visibility");

        $response->assertStatus(403);
    });
});
