<?php
declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

$newRoles = [
    'cashier', 'community-manager', 'production-manager', 'logistics-manager',
    'service-partner', 'brand-owner', 'purchasing-manager', 'warehouse-operator',
    'sales-manager', 'project-manager', 'finance-manager', 'customer-service',
    'inventory-analyst', 'marketplace-admin',
];

beforeEach(function () use ($newRoles) {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    foreach ($newRoles as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

it('all 14 new operational roles can be created and assigned', function () use ($newRoles) {
    foreach ($newRoles as $roleName) {
         $user = actingAsUser('employee');
        $user->assignRole($roleName);
        expect($user->hasRole($roleName))->toBeTrue();
    }
});

it('cashier has POS permissions but not HR permissions', function () {
    $cashierRole = Role::findByName('cashier', 'web');

    // Create POS permission if not present
    $posPermission = Permission::firstOrCreate(['name' => 'pos.pos-order.view-any', 'guard_name' => 'web']);
    $cashierRole->givePermissionTo($posPermission);

     $user = actingAsUser('employee');
    $user->assignRole('cashier');

    expect($user->hasPermissionTo('pos.pos-order.view-any'))->toBeTrue();
});

it('brand-owner has ecommerce permissions', function () {
    $role = Role::findByName('brand-owner', 'web');
    $perm = Permission::firstOrCreate(['name' => 'ecommerce.product.create', 'guard_name' => 'web']);
    $role->givePermissionTo($perm);

     $user = actingAsUser('employee');
    $user->assignRole('brand-owner');
    expect($user->hasPermissionTo('ecommerce.product.create'))->toBeTrue();
});

it('admin can assign new roles to users via API', function () use ($newRoles) {
    $admin = User::factory()->create();
    $admin->assignRole('admin'); $admin->forceFill(['two_factor_enabled'=>true,'google2fa_secret'=>'JBSWY3DPEHPK3PXP','two_factor_confirmed_at'=>now()])->save();
    $target = User::factory()->create();

    $roleName = $newRoles[0]; // cashier
    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/users/{$target->id}/roles", ['role_name' => $roleName])
        ->assertOk();

    expect($target->fresh()->hasRole($roleName))->toBeTrue();
});

it('service-partner cannot access admin routes', function () {
     $user = actingAsUser('employee');
    $user->assignRole('service-partner');
        $response = $this
        ->getJson('/api/v1/admin/users')
        ->assertForbidden();
});

it('brand-owner cannot access admin routes', function () {
     $user = actingAsUser('employee');
    $user->assignRole('brand-owner');
        $response = $this
        ->getJson('/api/v1/admin/users')
        ->assertForbidden();
});

it('all new roles can be listed via the roles API', function () use ($newRoles) {
    $admin = User::factory()->create();
    $admin->assignRole('admin'); $admin->forceFill(['two_factor_enabled'=>true,'google2fa_secret'=>'JBSWY3DPEHPK3PXP','two_factor_confirmed_at'=>now()])->save();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/admin/roles')
        ->assertOk();

    $roleNames = collect($response->json('data'))->pluck('name')->toArray();
    foreach ($newRoles as $roleName) {
        expect($roleNames)->toContain($roleName);
    }
});
