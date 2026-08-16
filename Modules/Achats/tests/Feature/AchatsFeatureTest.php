<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseOrderLine;
use Modules\Achats\Models\Supplier;
use Modules\Achats\Models\RFQ;
use Modules\Achats\Services\PurchaseOrderService;
use Modules\Achats\Services\SupplierService;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function achatsUser(): User
{
    return actingAsUser('admin');
}

function createSupplier(array $overrides = []): Supplier
{
    $user = User::first() ?? User::factory()->create();

    return Supplier::create(array_merge([
        'code'       => 'SUP-' . uniqid(),
        'name'       => 'Test Supplier',
        'email'      => 'supplier@example.com',
        'phone'      => '+2250700000000',
        'country'    => 'CI',
        'currency'   => 'XOF',
        'is_active'  => true,
        'created_by' => $user->id,
    ], $overrides));
}

function createPurchaseOrder(Supplier $supplier, array $overrides = []): PurchaseOrder
{
    $user = User::first() ?? User::factory()->create();

    return PurchaseOrder::create(array_merge([
        'po_number'   => 'PO-' . uniqid(),
        'supplier_id' => $supplier->id,
        'status'      => 'draft',
        'order_date'  => now()->toDateString(),
        'currency'    => 'XOF',
        'subtotal'    => 0,
        'tax_amount'  => 0,
        'total'       => 0,
        'created_by'  => $user->id,
    ], $overrides));
}

// ─── Authentication ────────────────────────────────────────────────────────────

test('unauthenticated request to purchase orders returns 401', function () {
    $this->getJson('/api/v1/achats/purchase-orders')
        ->assertUnauthorized();
});

test('unauthenticated request to suppliers returns 401', function () {
    $this->getJson('/api/v1/achats/suppliers')
        ->assertUnauthorized();
});

// ─── Supplier Service ──────────────────────────────────────────────────────────

test('can create a supplier via SupplierService', function () {
    $user    = achatsUser();
    $service = app(SupplierService::class);

    $supplier = $service->createSupplier([
        'name'       => 'Acme Fournisseur',
        'email'      => 'contact@acme.ci',
        'country'    => 'CI',
        'created_by' => $user->id,
    ]);

    expect($supplier)->toBeInstanceOf(Supplier::class)
        ->and($supplier->name)->toBe('Acme Fournisseur')
        ->and($supplier->is_active)->toBeTrue()
        ->and($supplier->code)->toStartWith('SUP-');
});

test('can deactivate and reactivate a supplier', function () {
    achatsUser();
    $service  = app(SupplierService::class);
    $supplier = createSupplier(['is_active' => true]);

    $service->deactivateSupplier($supplier);
    expect($supplier->fresh()->is_active)->toBeFalse();

    $service->activateSupplier($supplier);
    expect($supplier->fresh()->is_active)->toBeTrue();
});

test('getActiveSuppliers returns only active suppliers', function () {
    achatsUser();
    $service = app(SupplierService::class);

    createSupplier(['is_active' => true]);
    createSupplier(['is_active' => true]);
    createSupplier(['is_active' => false]);

    $active = $service->getActiveSuppliers();

    expect($active->every(fn ($s) => $s->is_active))->toBeTrue()
        ->and($active->count())->toBeGreaterThanOrEqual(2);
});

// ─── PurchaseOrder Service ─────────────────────────────────────────────────────

test('can create a purchase order via PurchaseOrderService', function () {
    $user     = achatsUser();
    $supplier = createSupplier();
    $service  = app(PurchaseOrderService::class);

    $po = $service->createPurchaseOrder([
        'supplier_id' => $supplier->id,
        'order_date'  => now()->toDateString(),
        'currency'    => 'XOF',
        'created_by'  => $user->id,
    ]);

    expect($po)->toBeInstanceOf(PurchaseOrder::class)
        ->and($po->po_number)->toStartWith('PO-')
        ->and($po->status)->toBe('draft');
});

test('purchase order isDraft returns true for draft status', function () {
    achatsUser();
    $supplier = createSupplier();
    $po       = createPurchaseOrder($supplier, ['status' => 'draft']);

    expect($po->isDraft())->toBeTrue();
});

test('purchase order isDraft returns false for submitted status', function () {
    achatsUser();
    $supplier = createSupplier();
    $po       = createPurchaseOrder($supplier, ['status' => 'submitted']);

    expect($po->isDraft())->toBeFalse();
});

test('cannot update a non-draft purchase order', function () {
    achatsUser();
    $supplier = createSupplier();
    $po       = createPurchaseOrder($supplier, ['status' => 'submitted']);
    $service  = app(PurchaseOrderService::class);

    expect(fn () => $service->updatePurchaseOrder($po, ['notes' => 'Updated']))
        ->toThrow(\Exception::class);
});

test('can add a line item to a draft purchase order', function () {
    achatsUser();
    $supplier = createSupplier();
    $po       = createPurchaseOrder($supplier);
    $service  = app(PurchaseOrderService::class);

    $line = $service->addLineItem($po, [
        'description' => 'Bureau ergonomique',
        'quantity'    => 2,
        'unit_price'  => 75000,
    ]);

    expect($line)->toBeInstanceOf(PurchaseOrderLine::class)
        ->and($line->line_total)->toEqual(150000)
        ->and($po->lines()->count())->toBe(1);
});

test('cannot add line item to non-draft purchase order', function () {
    achatsUser();
    $supplier = createSupplier();
    $po       = createPurchaseOrder($supplier, ['status' => 'approved']);
    $service  = app(PurchaseOrderService::class);

    expect(fn () => $service->addLineItem($po, [
        'description' => 'Chaise',
        'quantity'    => 1,
        'unit_price'  => 50000,
    ]))->toThrow(\Exception::class);
});

// ─── API Endpoints ─────────────────────────────────────────────────────────────

test('authenticated user can list purchase orders', function () {
    achatsUser();

    $this->getJson('/api/v1/achats/purchase-orders')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('authenticated user can list suppliers', function () {
    achatsUser();

    $this->getJson('/api/v1/achats/suppliers')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('can create a purchase order via API', function () {
    $user     = achatsUser();
    $supplier = createSupplier();

    $this->postJson('/api/v1/achats/purchase-orders', [
        'supplier_id' => $supplier->id,
        'order_date'  => now()->toDateString(),
        'currency'    => 'XOF',
    ])
        ->assertCreated()
        ->assertJsonPath('status', 'draft');
});

test('can create a supplier via API', function () {
    achatsUser();

    $this->postJson('/api/v1/achats/suppliers', [
        'name'    => 'Nouveau Fournisseur',
        'email'   => 'nouveau@example.com',
        'country' => 'SN',
    ])
        ->assertCreated()
        ->assertJsonPath('name', 'Nouveau Fournisseur');
});

// ─── RFQ ──────────────────────────────────────────────────────────────────────

test('can create an RFQ via API', function () {
    achatsUser();

    $this->postJson('/api/v1/achats/rfqs', [
        'description'      => 'Request for office supplies',
        'required_by_date' => now()->addDays(30)->toDateString(),
    ])
        ->assertCreated()
        ->assertJsonPath('status', 'draft');
});

test('RFQ listing returns 200', function () {
    achatsUser();

    $this->getJson('/api/v1/achats/rfqs')
        ->assertOk()
        ->assertJsonStructure(['data']);
});
