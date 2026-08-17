<?php

declare(strict_types=1);

namespace Modules\BI\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chantier 8.2 (BI): ExternalDataSource subsystem wiring.
 *
 * `ExternalDataSourceController` (18 methods — external integrations:
 * connect/disconnect, credentials, field mapping, sync config, transform
 * data, sync history) and `ExternalDataPolicy` were already fully written
 * but unwired: no route, no page, and — the real gap — no migration for
 * any of the 6 backing models (`ExternalDataSource`, `ExternalCredential`,
 * `FieldMapping`, `SyncConfiguration`, `SyncHistory`, `TransformationRule`).
 */
class Chantier82BiExternalDataSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_data_sources_page_renders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/bi/external-data-sources');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('BI/ExternalDataSources/Index', false));
    }

    public function test_admin_can_create_external_data_source(): void
    {
        $this->actingAsUser('admin');

        $response = $this->postJson('/api/v1/bi/external-data-sources', [
            'name'                => 'Google Analytics — Site principal',
            'description'         => 'Suivi du trafic web du site vitrine',
            'source_type'         => 'google_analytics',
            'authentication_type' => 'oauth',
            'connection_config'   => ['property_id' => 'GA-123456'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('bi_external_data_sources', [
            'name'                => 'Google Analytics — Site principal',
            'source_type'         => 'google_analytics',
            'authentication_type' => 'oauth',
            'status'              => 'inactive',
        ]);
    }

    public function test_user_without_permission_cannot_create_external_data_source(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/bi/external-data-sources', [
            'name'                => 'Unauthorized Source',
            'source_type'         => 'shopify',
            'authentication_type' => 'api_key',
            'connection_config'   => ['shop' => 'example.myshopify.com'],
        ]);

        $response->assertForbidden();
    }
}
