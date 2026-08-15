<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Logistics\Models\CustomsDeclaration;
use Modules\Logistics\Models\Shipment;
use Spatie\Permission\Models\Role;


beforeEach(function () {
    $this->user = User::factory()->create();
    Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
    $this->user->assignRole('logistics-manager');
    $this->shipment = Shipment::factory()->create();
});

test('can create customs declaration', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/logistics/customs-declarations', [
            'shipment_id' => $this->shipment->id,
            'hs_code' => '9403.20.00',
            'item_description' => 'Office chair',
            'quantity' => 5,
            'declared_value' => 250.00,
            'currency' => 'USD',
            'country_of_origin' => 'CN',
            'declaration_number' => 'CUST-001',
        ]);

    expect($response->status())->toBe(201);
    expect($response->json('data.hs_code'))->toBe('9403.20.00');
});

test('can list customs declarations', function () {
    CustomsDeclaration::factory(3)->create(['shipment_id' => $this->shipment->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/logistics/customs-declarations');

    expect($response->status())->toBe(200);
    expect($response->json('meta.total'))->toBe(3);
});

test('can get single declaration', function () {
    $declaration = CustomsDeclaration::factory()->create(['shipment_id' => $this->shipment->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/logistics/customs-declarations/{$declaration->id}");

    expect($response->status())->toBe(200);
    expect($response->json('data.id'))->toBe($declaration->id);
});

test('validates hs code format', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/logistics/customs-declarations', [
            'shipment_id' => $this->shipment->id,
            'hs_code' => 'INVALID-CODE',
            'item_description' => 'Item',
            'declared_value' => 100,
            'currency' => 'USD',
            'country_of_origin' => 'US',
        ]);

    expect($response->status())->toBe(422);
});

test('validates declared value positive', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/logistics/customs-declarations', [
            'shipment_id' => $this->shipment->id,
            'hs_code' => '6204.62.20',
            'item_description' => 'T-shirt',
            'declared_value' => -50,
            'currency' => 'USD',
            'country_of_origin' => 'IN',
        ]);

    expect($response->status())->toBe(422);
});

test('can update declaration', function () {
    $declaration = CustomsDeclaration::factory()->create(['declared_value' => 100]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/logistics/customs-declarations/{$declaration->id}", [
            'declared_value' => 150,
        ]);

    expect($response->status())->toBe(200);
    expect($response->json('data.declared_value'))->toBe(150);
});

test('can delete declaration', function () {
    $declaration = CustomsDeclaration::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/logistics/customs-declarations/{$declaration->id}");

    expect($response->status())->toBe(200);
});

test('validates required fields', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/logistics/customs-declarations', []);

    expect($response->status())->toBe(422);
});

test('validates unique declaration number', function () {
    CustomsDeclaration::factory()->create(['declaration_number' => 'CUST-DUP-001']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/logistics/customs-declarations', [
            'shipment_id' => $this->shipment->id,
            'hs_code' => '6204.62.20',
            'item_description' => 'T-shirt',
            'declared_value' => 25,
            'currency' => 'USD',
            'country_of_origin' => 'IN',
            'declaration_number' => 'CUST-DUP-001',
        ]);

    expect($response->status())->toBe(422);
});

test('cannot access without auth', function () {
    $response = $this->getJson('/api/v1/logistics/customs-declarations');
    expect($response->status())->toBe(401);
});
