<?php

use App\Models\User;
use App\Services\RolePermissionService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->service = app(RolePermissionService::class);

    // Create test roles and permissions
    Role::truncate();
    Permission::truncate();

    $this->superAdminRole = Role::create(['name' => 'super-admin']);
    $this->adminRole = Role::create(['name' => 'admin']);
    $this->salesRole = Role::create(['name' => 'sales-rep']);

    Permission::create(['name' => 'crm.contact.view']);
    Permission::create(['name' => 'crm.contact.create']);
    Permission::create(['name' => 'crm.contact.delete']);
    Permission::create(['name' => 'accounting.invoice.view']);

    $this->adminRole->givePermissionTo(['crm.contact.view', 'crm.contact.create', 'crm.contact.delete']);
    $this->salesRole->givePermissionTo(['crm.contact.view', 'crm.contact.create']);
});

it('checks if user has permission for resource action', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    expect($this->service->hasPermission($admin, 'crm', 'contact', 'view'))->toBeTrue();
    expect($this->service->hasPermission($admin, 'crm', 'contact', 'delete'))->toBeTrue();
    expect($this->service->hasPermission($admin, 'accounting', 'invoice', 'view'))->toBeFalse();
});

it('checks if user has module access', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    expect($this->service->hasModuleAccess($admin, 'crm'))->toBeTrue();
    expect($this->service->hasModuleAccess($admin, 'accounting'))->toBeFalse();
});

it('retrieves user modules', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $modules = $this->service->getUserModules($admin);

    expect($modules->contains('crm'))->toBeTrue();
});

it('retrieves module resources for user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $resources = $this->service->getModuleResources($admin, 'crm');

    expect($resources->contains('contact'))->toBeTrue();
});

it('retrieves resource actions for user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $actions = $this->service->getResourceActions($admin, 'crm', 'contact');

    expect($actions)->toContain('view', 'create', 'delete');
});

it('super admin has all permissions', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    expect($this->service->hasPermission($superAdmin, 'crm', 'contact', 'view'))->toBeTrue();
    expect($this->service->hasPermission($superAdmin, 'crm', 'contact', 'delete'))->toBeTrue();
    expect($this->service->hasModuleAccess($superAdmin, 'accounting'))->toBeTrue();
});

it('assigns role to user', function () {
    $user = User::factory()->create();

    $this->service->assignRoleToUser($user, 'sales-rep');

    expect($user->hasRole('sales-rep'))->toBeTrue();
});

it('revokes role from user', function () {
    $user = User::factory()->create();
    $user->assignRole('sales-rep');

    $this->service->revokeRoleFromUser($user, 'sales-rep');

    expect($user->hasRole('sales-rep'))->toBeFalse();
});

it('gets user roles', function () {
    $user = User::factory()->create();
    $user->assignRole(['admin', 'sales-rep']);

    $roles = $this->service->getUserRoles($user);

    expect($roles)->toContain('admin', 'sales-rep');
});

it('checks if user has specific role', function () {
    $user = User::factory()->create();
    $user->assignRole('sales-rep');

    expect($this->service->hasRole($user, 'sales-rep'))->toBeTrue();
    expect($this->service->hasRole($user, 'admin'))->toBeFalse();
});

it('checks if user has any of multiple roles', function () {
    $user = User::factory()->create();
    $user->assignRole('sales-rep');

    expect($this->service->hasAnyRole($user, ['admin', 'sales-rep']))->toBeTrue();
    expect($this->service->hasAnyRole($user, ['admin', 'manager']))->toBeFalse();
});

it('creates or updates role', function () {
    $role = $this->service->createOrUpdateRole('new-role');

    expect(Role::where('name', 'new-role')->exists())->toBeTrue();

    $sameRole = $this->service->createOrUpdateRole('new-role');
    expect($sameRole->id)->toBe($role->id);
});

it('creates or updates permission', function () {
    $permission = $this->service->createOrUpdatePermission('test.resource.action');

    expect(Permission::where('name', 'test.resource.action')->exists())->toBeTrue();

    $samePermission = $this->service->createOrUpdatePermission('test.resource.action');
    expect($samePermission->id)->toBe($permission->id);
});

it('syncs role permissions', function () {
    $role = Role::create(['name' => 'test-role']);
    $permissions = ['crm.contact.view', 'crm.contact.create'];

    $this->service->syncRolePermissions($role, $permissions);

    expect($role->hasPermissionTo('crm.contact.view'))->toBeTrue();
    expect($role->hasPermissionTo('crm.contact.create'))->toBeTrue();
    expect($role->hasPermissionTo('crm.contact.delete'))->toBeFalse();
});
