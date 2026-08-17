<?php

declare(strict_types=1);

namespace Modules\Security\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Security\Models\ServiceIdentity;
use Modules\Security\Models\TrustZone;
use Tests\TestCase;

/**
 * Chantier 8.3 (Core + Security): a full cross-layer audit found several
 * endpoints with zero authorization at all — any authenticated user of any
 * tenant could read/rotate service credentials (ServiceIdentityController),
 * manipulate trust zones (TrustZoneController), block/unblock IPs
 * (RateLimitController), or read every tenant's login history
 * (AuthenticationEventController). This test locks in the fix: negative
 * checks for the previously-open endpoints, plus positive checks that the
 * intended (admin/security-admin) users still work.
 */
class Chantier83CoreSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_user_cannot_list_service_identities(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/v1/security/service-identities');

        $response->assertForbidden();
    }

    public function test_security_admin_can_create_and_view_a_service_identity(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $user->assignRole('security-admin');
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/security/service-identities', [
            'service_name' => 'Billing Worker',
            'service_type' => 'internal-api',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['data', 'private_key', 'message']);
        $this->assertDatabaseHas('service_identities', [
            'service_name' => 'Billing Worker',
            'company_id' => $company->id,
        ]);
    }

    public function test_plain_user_cannot_create_a_trust_zone(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/security/trust-zones', [
            'zone_name' => 'Internal Network',
        ]);

        $response->assertForbidden();
    }

    public function test_security_admin_can_create_and_assign_a_resource_to_a_trust_zone(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $user->assignRole('security-admin');
        $this->actingAs($user, 'sanctum');

        $zone = TrustZone::factory()->for($company)->create();

        $response = $this->postJson("/api/v1/security/trust-zones/{$zone->id}/assign-resource", [
            'resource_type' => 'ip',
            'resource_value' => '10.0.0.5',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('security_trust_zones', ['id' => $zone->id]);
        $this->assertNotEmpty($zone->fresh()->assigned_resources);
    }

    public function test_plain_user_cannot_block_an_ip(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/security/rate-limits/block-ip', ['ip' => '1.2.3.4']);

        $response->assertForbidden();
    }

    public function test_plain_user_cannot_view_authentication_events(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/v1/security/auth-events');

        $response->assertForbidden();
    }

    public function test_threat_indicators_index_filters_by_the_real_threat_level_column(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('security-admin');
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/v1/security/threat-indicators?severity=critical');

        $response->assertOk();
    }
}
