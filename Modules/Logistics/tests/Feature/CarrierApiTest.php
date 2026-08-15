<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Logistics\Models\Carrier;
use Modules\Logistics\Models\CarrierRate;
use Spatie\Permission\Models\Role;


beforeEach(function () {
    $this->user = User::factory()->create();
    Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
    $this->user->assignRole('logistics-manager');
});

test('can create carrier', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/logistics/carriers', [
            'name' => 'FastShip Express',
            'code' => 'FSE',
            'contact_email' => 'contact@fastship.com',
            'contact_phone' => '+1234567890',
            'status' => 'active',
        ]);

    expect($response->status())->toBe(201);
    expect($response->json('data.name'))->toBe('FastShip Express');
    $this->assertDatabaseHas('logistics_carriers', ['code' => 'FSE']);
});

test('can list carriers', function () {
    Carrier::factory(5)->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/logistics/carriers');

    expect($response->status())->toBe(200);
    expect($response->json('meta.total'))->toBe(5);
});

test('can get single carrier', function () {
    $carrier = Carrier::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/logistics/carriers/{$carrier->id}");

    expect($response->status())->toBe(200);
    expect($response->json('data.id'))->toBe($carrier->id);
});

test('can update carrier', function () {
    $carrier = Carrier::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/logistics/carriers/{$carrier->id}", [
            'name' => 'New Name',
            'status' => 'inactive',
        ]);

    expect($response->status())->toBe(200);
    expect($response->json('data.name'))->toBe('New Name');
});

test('can delete carrier', function () {
    $carrier = Carrier::factory()->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/logistics/carriers/{$carrier->id}");

    expect($response->status())->toBe(200);
});

test('can get carrier rates', function () {
    $carrier = Carrier::factory()->create();
    CarrierRate::factory(3)->create(['carrier_id' => $carrier->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/logistics/carrier-rates');

    expect($response->status())->toBe(200);
    expect(count($response->json('data')))->toBe(3);
});

test('validates required fields', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/logistics/carriers', []);

    expect($response->status())->toBe(422);
});

test('validates unique code', function () {
    Carrier::factory()->create(['code' => 'DUP']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/logistics/carriers', [
            'name' => 'Another Carrier',
            'code' => 'DUP',
            'contact_email' => 'test@test.com',
        ]);

    expect($response->status())->toBe(422);
});

test('can filter by status', function () {
    Carrier::factory(2)->create(['status' => 'active']);
    Carrier::factory(1)->create(['status' => 'inactive']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/logistics/carriers?status=active');

    expect($response->status())->toBe(200);
    expect($response->json('meta.total'))->toBe(2);
});

test('cannot access without auth', function () {
    $response = $this->getJson('/api/v1/logistics/carriers');
    expect($response->status())->toBe(401);
});
