<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\BI\Models\BiDataSource;


test('can create a mysql data source', function () {
    $user = actingAsUser('manager');

    $response = $this
        ->postJson('/api/v1/bi/data-sources', [
            'name' => 'Production MySQL',
            'type' => 'mysql',
            'connection_config' => [
                'host' => 'db.example.com',
                'port' => 3306,
                'database' => 'erp',
                'username' => 'readonly',
                'password' => 'secret',
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('name', 'Production MySQL')
        ->assertJsonPath('type', 'mysql');
});

test('connection_config is encrypted in DB', function () {
    $user = User::factory()->create();

    $source = BiDataSource::create([
        'name' => 'Encrypted Source',
        'type' => 'postgresql',
        'connection_config' => [
            'host' => 'secure-db.example.com',
            'password' => 'super-secret',
        ],
        'created_by' => $user->id,
        'status' => 'inactive',
    ]);

    // Raw DB value should not contain plaintext password
    $raw = DB::table('bi_data_sources')
        ->where('id', $source->id)
        ->value('connection_config');

    expect($raw)->not->toContain('super-secret');
    expect($raw)->not->toContain('"password"');
});

test('can test connection and get result', function () {
    $user = actingAsUser('manager');

    $source = BiDataSource::create([
        'name' => 'Test Source',
        'type' => 'rest_api',
        'connection_config' => ['base_url' => 'http://invalid-host-that-does-not-exist.local'],
        'created_by' => $user->id,
        'status' => 'inactive',
    ]);

    $response = $this
        ->postJson("/api/v1/bi/data-sources/{$source->id}/test");

    $response->assertOk()
        ->assertJsonStructure(['success', 'latency_ms', 'status']);
});

test('unauthenticated user cannot access data sources', function () {
    $this->getJson('/api/v1/bi/data-sources')
        ->assertUnauthorized();
});

// Chantier 19 Lot 5: StoreDataSourceRequest/UpdateDataSourceRequest's `type`
// validation used to be `in:mysql,postgres,sqlite,api,csv` — a vocabulary
// that matches none of DataSourceService::resolve()'s real connector
// registry ('mysql'/'postgresql'/'rest_api'/'csv'/'google_sheets'). The
// real DataSources/Index.vue create form sends 'postgresql'/'mssql'/'api'
// — 'postgresql' always 422'd, 'mssql' had no backend connector at all,
// and 'api' would pass creation but fatal at test/sync/schema time since
// resolve() only recognizes 'rest_api'. Confirmed empirically before the
// fix; now aligned to the real, working connector registry.
describe('data source type vocabulary matches the real connector registry', function () {
    beforeEach(fn () => actingAsUser('manager'));

    it('accepts postgresql (the real value the create form sends)', function () {
        $this->postJson('/api/v1/bi/data-sources', [
            'name' => 'Analytics Postgres',
            'type' => 'postgresql',
            'connection_config' => ['host' => 'db.example.com'],
        ])->assertStatus(201)->assertJsonPath('type', 'postgresql');
    });

    it('accepts rest_api (not the old, connector-mismatched "api" value)', function () {
        $this->postJson('/api/v1/bi/data-sources', [
            'name' => 'Stripe API',
            'type' => 'rest_api',
            'connection_config' => ['base_url' => 'https://api.stripe.com'],
        ])->assertStatus(201)->assertJsonPath('type', 'rest_api');
    });

    it('rejects the old "postgres" value now that the vocabulary is aligned', function () {
        $this->postJson('/api/v1/bi/data-sources', [
            'name' => 'Old Vocabulary',
            'type' => 'postgres',
        ])->assertStatus(422);
    });

    it('rejects mssql — no MssqlConnector exists anywhere in this module', function () {
        $this->postJson('/api/v1/bi/data-sources', [
            'name' => 'SQL Server',
            'type' => 'mssql',
        ])->assertStatus(422);
    });

    it('a real postgresql source created via the API can actually be resolved to its connector', function () {
        $response = $this->postJson('/api/v1/bi/data-sources', [
            'name' => 'Resolvable Postgres',
            'type' => 'postgresql',
            'connection_config' => ['host' => 'db.example.com', 'database' => 'erp'],
        ])->assertStatus(201);

        $source = BiDataSource::find($response->json('id'));

        expect(app(\Modules\BI\Services\DataSourceService::class)->resolve($source))
            ->toBeInstanceOf(\Modules\BI\Services\Connectors\PostgresConnector::class);
    });
});
