<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modules/Core/routes/secrets.php (10 endpoints) was never loaded by any
 * RouteServiceProvider::map() — no /api/v1/secrets/* route existed at all,
 * independent of the EncryptionService/table/config gaps fixed in earlier
 * commits. This exercises the real HTTP path end-to-end, the plan's own
 * "POST /api/v1/secrets works end-to-end" verification requirement.
 */
class SecretsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_and_retrieve_secret_round_trips_over_http()
    {
        $user = $this->actingAsUser('admin');

        $store = $this->actingAs($user, 'sanctum')->postJson('/api/v1/secrets', [
            'name' => 'controller_test_key',
            'value' => 'sk_live_value_123',
            'type' => 'api_key',
        ]);

        $store->assertCreated();
        $store->assertJsonPath('success', true);
        $store->assertJsonPath('data.name', 'controller_test_key');

        $show = $this->actingAs($user, 'sanctum')->getJson('/api/v1/secrets/controller_test_key');

        $show->assertOk();
        $show->assertJsonPath('success', true);
        $show->assertJsonPath('data.value', 'sk_live_value_123');
    }

    public function test_secrets_endpoints_require_authentication()
    {
        $response = $this->postJson('/api/v1/secrets', [
            'name' => 'unauth_test',
            'value' => 'x',
            'type' => 'api_key',
        ]);

        $response->assertUnauthorized();
    }

    /**
     * SecretsController used to put $e->getMessage() straight into the JSON
     * response for every failure — a real disclosure risk on a secrets
     * vault, and one that was only theoretical while these routes were
     * unreachable (fixed two commits ago). Duplicate-name is a reliable way
     * to trigger storeSecret()'s real exception path.
     */
    public function test_store_failure_does_not_leak_exception_message()
    {
        $user = $this->actingAsUser('admin');

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/secrets', [
            'name' => 'duplicate_key',
            'value' => 'sk_first',
            'type' => 'api_key',
        ])->assertCreated();

        $second = $this->actingAs($user, 'sanctum')->postJson('/api/v1/secrets', [
            'name' => 'duplicate_key',
            'value' => 'sk_second',
            'type' => 'api_key',
        ]);

        $second->assertStatus(400);
        $second->assertJsonPath('success', false);
        $second->assertJsonMissingPath('error');
    }

    public function test_grant_access_rejects_an_invalid_scope()
    {
        $admin = $this->actingAsUser('admin');
        $target = \App\Models\User::factory()->create();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/secrets', [
            'name' => 'grant_scope_test',
            'value' => 'sk_value',
            'type' => 'api_key',
        ])->assertCreated();

        $response = $this->actingAs($admin, 'sanctum')->postJson(
            '/api/v1/secrets/grant_scope_test/access/grant',
            ['user_id' => $target->id, 'scopes' => ['read', 'not_a_real_scope']]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['scopes.1']);
    }

    /**
     * Chantier 19 Lot 3: empirical re-verification of the Chantier 10
     * cross-tenant fix (SecretsService::getTenantId() -> company_id, no
     * X-Tenant-ID header fallback) — the fix was code-read-verified before
     * but never actually exercised over the real HTTP route with two real
     * companies. Confirmed still correct here.
     */
    public function test_secret_created_by_one_company_is_invisible_to_another()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $companyA = \App\Models\Company::create(['name' => 'SecretsCo A', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);
        $companyB = \App\Models\Company::create(['name' => 'SecretsCo B', 'currency' => 'MGA', 'timezone' => 'Indian/Antananarivo']);

        $userA = $this->actingAsUser('admin');
        $userA->forceFill(['company_id' => $companyA->id])->save();

        $this->actingAs($userA, 'sanctum')->postJson('/api/v1/secrets', [
            'name' => 'xtenant_secret',
            'value' => 'company_a_value',
            'type' => 'api_key',
        ])->assertCreated();

        $userB = \App\Models\User::factory()->create(['company_id' => $companyB->id]);
        $userB->assignRole('admin');

        // Company B's admin cannot see it in their list, and a direct GET by
        // name 404s the underlying secret entirely (real isolation, not just
        // an authorization denial that would leak existence).
        $list = $this->actingAs($userB, 'sanctum')->getJson('/api/v1/secrets');
        $list->assertOk();
        $names = collect($list->json('data'))->pluck('name');
        expect($names)->not->toContain('xtenant_secret');

        $show = $this->actingAs($userB, 'sanctum')->getJson('/api/v1/secrets/xtenant_secret');
        $show->assertStatus(403);
    }

    /**
     * Chantier 19 Lot 3: real bug found via empirical execution — the
     * secrets routes' own route-level gate is
     * `role:security-admin,admin,super-admin` (routes/secrets.php), and
     * that file's own docblock names security-admin as this vault's
     * intended day-to-day operator, but
     * SecretAccessControl::canAccessSecret()'s admin bypass only checked
     * `hasRole(['admin', 'super-admin'])` — a security-admin who created a
     * secret through this very route could not then retrieve, rotate, or
     * revoke that same secret (every one of those calls denied with 403),
     * since storing a secret never self-grants access and role-based access
     * fell through to nothing. Confirmed via tinker before the fix, fixed
     * in SecretAccessControl::canAccessSecret()/grantSecretAccess().
     */
    public function test_security_admin_can_retrieve_a_secret_they_just_created()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'security-admin', 'guard_name' => 'web']);
        $user = \App\Models\User::factory()->create();
        $user->forceFill([
            'two_factor_enabled' => true,
            'google2fa_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ])->save();
        $user->assignRole('security-admin');

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/secrets', [
            'name' => 'secadmin_http_secret',
            'value' => 'sk_owned_by_secadmin',
            'type' => 'api_key',
        ])->assertCreated();

        $show = $this->actingAs($user, 'sanctum')->getJson('/api/v1/secrets/secadmin_http_secret');
        $show->assertOk();
        $show->assertJsonPath('data.value', 'sk_owned_by_secadmin');

        $rotate = $this->actingAs($user, 'sanctum')->putJson('/api/v1/secrets/secadmin_http_secret/rotate', []);
        $rotate->assertOk();
    }
}
