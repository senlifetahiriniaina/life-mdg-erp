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
}
