<?php

declare(strict_types=1);

use Modules\Accounting\Models\VatDeclaration;


test('unauthenticated user cannot list vat declarations', function () {
    $this->getJson('/api/v1/accounting/vat-declarations')
        ->assertUnauthorized();
});

test('authenticated user can list vat declarations', function () {
    actingAsUser('accountant');

    $this->getJson('/api/v1/accounting/vat-declarations')
        ->assertOk()
        ->assertJsonStructure(['data', 'total']);
});

test('authenticated user can calculate a vat declaration', function () {
    actingAsUser('accountant');

    $this->postJson('/api/v1/accounting/vat-declarations/calculate', [
        'year' => 2026,
        'period' => 1,
        'type' => 'monthly',
    ])
        ->assertStatus(201)
        ->assertJsonPath('period_type', 'monthly')
        ->assertJsonPath('period_year', 2026)
        ->assertJsonPath('period_number', 1);
});

test('authenticated user can view a vat declaration', function () {
    actingAsUser('accountant');

    $decl = VatDeclaration::create([
        'period_type' => 'monthly',
        'period_year' => 2026,
        'period_number' => 1,
        'status' => 'draft',
        'total_sales' => 10000,
        'total_purchases' => 5000,
        'vat_collected' => 2000,
        'vat_deductible' => 1000,
        'vat_due' => 1000,
    ]);

    $this->getJson("/api/v1/accounting/vat-declarations/{$decl->id}")
        ->assertOk()
        ->assertJsonPath('id', $decl->id);
});

test('authenticated user can submit a vat declaration', function () {
    actingAsUser('accountant');

    $decl = VatDeclaration::create([
        'period_type' => 'monthly',
        'period_year' => 2026,
        'period_number' => 2,
        'status' => 'draft',
        'total_sales' => 5000,
        'total_purchases' => 2000,
        'vat_collected' => 1000,
        'vat_deductible' => 400,
        'vat_due' => 600,
    ]);

    $this->postJson("/api/v1/accounting/vat-declarations/{$decl->id}/submit", [
        'reference' => 'REF-2026-01',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'submitted');
});

test('authenticated user cannot submit an already submitted declaration', function () {
    actingAsUser('accountant');

    $decl = VatDeclaration::create([
        'period_type' => 'monthly',
        'period_year' => 2026,
        'period_number' => 3,
        'status' => 'submitted',
        'total_sales' => 5000,
        'total_purchases' => 2000,
        'vat_collected' => 1000,
        'vat_deductible' => 400,
        'vat_due' => 600,
        'reference' => 'REF-EXISTING',
        'submitted_at' => now(),
    ]);

    $this->postJson("/api/v1/accounting/vat-declarations/{$decl->id}/submit", [
        'reference' => 'REF-NEW',
    ])
        ->assertStatus(422);
});

test('authenticated user can create a vat rate', function () {
    actingAsUser('accountant');

    $this->postJson('/api/v1/accounting/vat-rates', [
        'name' => 'Standard 20%',
        'rate' => 20,
        'country_code' => 'FR',
        'applies_from' => '2024-01-01',
        'type' => 'standard',
        'is_default' => true,
    ])
        ->assertStatus(201)
        ->assertJsonPath('rate', '20.00');
});
