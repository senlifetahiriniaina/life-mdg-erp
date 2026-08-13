<?php
declare(strict_types=1);
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

it('admin can list roles', function () {
     $user = actingAsUser('employee');
     $user->assignRole('admin');

     $this->getJson('/api/v1/admin/roles')
         ->assertOk()
         ->assertJsonStructure(['data', 'total']);
});
it('admin can list users with roles', function () {
     $user = actingAsUser('employee');
     $user->assignRole('admin');
    User::factory()->count(3)->create();

    $this->getJson('/api/v1/admin/users?with=roles')
        ->assertOk()
        ->assertJsonStructure(['data', 'total']);
});
it('admin can assign a role to a user', function () {
    $admin = User::factory()->create(); $admin->assignRole('admin'); $admin->forceFill(['two_factor_enabled'=>true,'google2fa_secret'=>'JBSWY3DPEHPK3PXP','two_factor_confirmed_at'=>now()])->save();
    $target = User::factory()->create();
    $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/users/{$target->id}/roles", ['role_name'=>'manager'])->assertOk();
    expect($target->fresh()->hasRole('manager'))->toBeTrue();
});
it('admin can revoke a role from a user', function () {
    $admin = User::factory()->create(); $admin->assignRole('admin'); $admin->forceFill(['two_factor_enabled'=>true,'google2fa_secret'=>'JBSWY3DPEHPK3PXP','two_factor_confirmed_at'=>now()])->save();
    $target = User::factory()->create(); $target->assignRole('employee');
    $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/admin/users/{$target->id}/roles/employee")->assertOk();
    expect($target->fresh()->hasRole('employee'))->toBeFalse();
});
it('admin can list permissions', function () {
     $user = actingAsUser('employee');
     $user->assignRole('admin');

     $this->getJson('/api/v1/admin/permissions')
         ->assertOk()
         ->assertJsonStructure(['data', 'total']);
});
