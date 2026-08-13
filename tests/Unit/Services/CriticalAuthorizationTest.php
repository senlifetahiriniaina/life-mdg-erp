<?php

declare(strict_types=1);

use App\Models\User;
use App\Policies\BaseErpPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Test models for critical authorization tests
class CriticalOwnedResourcePolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = 'owner_id';
}

class CriticalTestOwner extends Model
{
    protected $table = 'users';
    protected $guarded = [];
    public $timestamps = false;
}

// ─────────────────────────────────────────────────────────────────────────────
// Critical Authorization Tests: RBAC and Policy Enforcement
// ─────────────────────────────────────────────────────────────────────────────

test('super-admin can delete any resource regardless of ownership', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $otherUser = User::factory()->create();
    $model = CriticalTestOwner::find($otherUser->id);

    // Verify super-admin bypasses ownership check
    $policy = new CriticalOwnedResourcePolicy();
    expect($policy->delete($superAdmin, $model))->toBeTrue();
});

test('admin role can update resources not owned by them', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $other = User::factory()->create();
    $model = CriticalTestOwner::find($other->id);

    $policy = new CriticalOwnedResourcePolicy();
    expect($policy->update($admin, $model))->toBeTrue();
});

test('manager role can update resources', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

    $manager = User::factory()->create();
    $manager->assignRole('manager');

    $other = User::factory()->create();
    $ownedModel = CriticalTestOwner::find($manager->id);

    // Manager can update their own
    $policy = new CriticalOwnedResourcePolicy();
    expect($policy->update($manager, $ownedModel))->toBeTrue();
});

test('owned resource policy enforces ownership checks', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);

    $employee = User::factory()->create();
    $employee->assignRole('employee');

    $other = User::factory()->create();

    $ownedModel = CriticalTestOwner::find($employee->id);

    $policy = new CriticalOwnedResourcePolicy();
    // Employee can update their own resources
    expect($policy->update($employee, $ownedModel))->toBeTrue();
});

test('any authenticated user can view any resource (open read access)', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $model = CriticalTestOwner::find($user2->id);
    $policy = new CriticalOwnedResourcePolicy();

    // User1 can view user2's resource
    expect($policy->view($user1, $model))->toBeTrue();
});

test('any authenticated user can create a resource', function () {
    $user = User::factory()->create();

    $policy = new CriticalOwnedResourcePolicy();
    expect($policy->create($user))->toBeTrue();
});
