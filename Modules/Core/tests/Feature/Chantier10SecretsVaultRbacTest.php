<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Chantier 10 — the secrets vault (`/api/v1/secrets/*`) had two independent
 * real, live holes:
 *  1. SecretsService/SecretAccessControl/SecretRotationManager::getTenantId()
 *     resolved `auth()->user()?->tenant_id ?? request()->header('X-Tenant-ID')`
 *     — since `tenant_id` is the well-documented phantom column, any
 *     authenticated user could set that header to read/create/rotate/revoke
 *     another tenant's secrets.
 *  2. The route group had no module:/role: gate at all (only auth:sanctum),
 *     and SecretsController never called the already-written
 *     SecretAccessControl::canAccessSecret() — any authenticated user of
 *     any role could reach every endpoint.
 * Both are fixed; this locks in the real HTTP behavior, not just a code read.
 */
class Chantier10SecretsVaultRbacTest extends TestCase
{
    use RefreshDatabase;

    private function userWithCompany(string $role): User
    {
        if (\Spatie\Permission\Models\Permission::count() === 0) {
            $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        }

        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_a_spoofed_x_tenant_id_header_cannot_reach_another_tenants_secret()
    {
        $victim = $this->userWithCompany('admin');
        $attacker = $this->userWithCompany('admin');

        $this->actingAs($victim, 'sanctum')->postJson('/api/v1/secrets', [
            'name' => 'chantier10_cross_tenant_secret',
            'value' => 'victim_secret_value',
            'type' => 'api_key',
        ])->assertCreated();

        // Attacker is a real admin of their own (different) tenant, and tries
        // to read the victim's secret by spoofing the tenant header — this
        // used to work because getTenantId() fell through to it.
        $response = $this->actingAs($attacker, 'sanctum')
            ->withHeaders(['X-Tenant-ID' => (string) $victim->company_id])
            ->getJson('/api/v1/secrets/chantier10_cross_tenant_secret');

        $response->assertStatus(403);

        // The victim, from their own real tenant, can still read it fine.
        $this->actingAs($victim, 'sanctum')
            ->getJson('/api/v1/secrets/chantier10_cross_tenant_secret')
            ->assertOk()
            ->assertJsonPath('data.value', 'victim_secret_value');
    }

    public function test_a_role_with_no_secrets_permission_is_denied_at_the_route_gate()
    {
        // sales-rep only ever gets crm.* permissions in RolesAndPermissionsSeeder
        // — same "definitely-not-authorized" precedent already used elsewhere
        // this session (Logistics/HR RBAC regression tests).
        $user = $this->userWithCompany('sales-rep');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/secrets');

        $response->assertStatus(403);
    }

    public function test_an_admin_can_still_use_the_vault_end_to_end()
    {
        $admin = $this->userWithCompany('admin');

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/secrets', [
            'name' => 'chantier10_admin_ok',
            'value' => 'still_works',
            'type' => 'api_key',
        ])->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/secrets/chantier10_admin_ok')
            ->assertOk()
            ->assertJsonPath('data.value', 'still_works');
    }
}
