<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\Product;

uses(RefreshDatabase::class);

test('the tenant global scope is registered on tenant-scoped models', function () {
    expect(array_key_exists('tenant', (new Product())->getGlobalScopes()))->toBeTrue();
});

test('the tenant scope is a no-op when there is no active tenant context', function () {
    // No stancl tenant initialised in unit tests, so queries must not be
    // constrained by tenant_id (otherwise seeders / console would break).
    $sql = Product::query()->toSql();

    expect($sql)->not->toContain('tenant_id');
});

test('withoutTenantScope returns an unscoped builder', function () {
    $sql = Product::withoutTenantScope()->toSql();

    expect($sql)->not->toContain('tenant_id');
});
