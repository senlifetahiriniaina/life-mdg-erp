<?php

declare(strict_types=1);

use App\Models\User;
use App\Policies\BaseErpPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Test double — a minimal policy with owner column ──────────────────────────

class OwnedResourcePolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = 'owner_id';
}

class SharedResourcePolicy extends BaseErpPolicy
{
    protected ?string $ownerColumn = null;
}

class OwnedModel extends Model
{
    protected $table = 'users'; // reuse users table for a lightweight test double
    protected $guarded = [];
}

// ── viewAny / view — always open ──────────────────────────────────────────────

test('viewAny is always allowed for any authenticated user', function () {
    $user   = User::factory()->create();
    $policy = new OwnedResourcePolicy();

    expect($policy->viewAny($user))->toBeTrue();
});

test('view is always allowed for any authenticated user', function () {
    $user   = User::factory()->create();
    $model  = new OwnedModel(['owner_id' => 999]);
    $policy = new OwnedResourcePolicy();

    expect($policy->view($user, $model))->toBeTrue();
});

// ── create — always allowed ───────────────────────────────────────────────────

test('create is allowed for any authenticated user', function () {
    $user   = User::factory()->create();
    $policy = new OwnedResourcePolicy();

    expect($policy->create($user))->toBeTrue();
});

// ── update — ownership ────────────────────────────────────────────────────────

test('update is allowed when user owns the record', function () {
    $user   = User::factory()->create();
    $model  = new OwnedModel(['owner_id' => $user->id]);
    $policy = new OwnedResourcePolicy();

    expect($policy->update($user, $model))->toBeTrue();
});

test('update is denied when user does not own the record', function () {
    $user   = User::factory()->create();
    $other  = User::factory()->create();
    $model  = new OwnedModel(['owner_id' => $other->id]);
    $policy = new OwnedResourcePolicy();

    expect($policy->update($user, $model))->toBeFalse();
});

test('update is allowed for admin role regardless of ownership', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user   = User::factory()->create();
    $user->assignRole('admin');
    $model  = new OwnedModel(['owner_id' => 999]); // different owner
    $policy = new OwnedResourcePolicy();

    expect($policy->update($user, $model))->toBeTrue();
});

test('update is allowed for manager role regardless of ownership', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
    $user  = User::factory()->create();
    $user->assignRole('manager');
    $model = new OwnedModel(['owner_id' => 999]);
    $policy = new OwnedResourcePolicy();

    expect($policy->update($user, $model))->toBeTrue();
});

// ── delete — ownership ────────────────────────────────────────────────────────

test('delete is allowed when user owns the record', function () {
    $user   = User::factory()->create();
    $model  = new OwnedModel(['owner_id' => $user->id]);
    $policy = new OwnedResourcePolicy();

    expect($policy->delete($user, $model))->toBeTrue();
});

test('delete is denied when user does not own the record', function () {
    $user   = User::factory()->create();
    $other  = User::factory()->create();
    $model  = new OwnedModel(['owner_id' => $other->id]);
    $policy = new OwnedResourcePolicy();

    expect($policy->delete($user, $model))->toBeFalse();
});

test('delete is allowed for super-admin via Gate::before bypass', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $user  = User::factory()->create();
    $user->assignRole('super-admin');
    $model = new OwnedModel(['owner_id' => 999]);
    $policy = new OwnedResourcePolicy();

    // super-admin bypasses Gate::before, but at policy level admin check covers it
    // via hasAnyRole(['super-admin', 'admin', 'manager'])
    expect($policy->delete($user, $model))->toBeTrue();
});

// ── Shared resources (ownerColumn = null) ────────────────────────────────────

test('update on shared resource is allowed regardless of user', function () {
    $user   = User::factory()->create();
    $model  = new OwnedModel(['owner_id' => 999]);
    $policy = new SharedResourcePolicy();

    // ownerColumn = null means no ownership check — any user can mutate
    expect($policy->update($user, $model))->toBeTrue();
});

test('delete on shared resource is allowed regardless of user', function () {
    $user   = User::factory()->create();
    $model  = new OwnedModel(['owner_id' => 999]);
    $policy = new SharedResourcePolicy();

    expect($policy->delete($user, $model))->toBeTrue();
});
