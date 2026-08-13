<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\BI\Models\BiDataSource;
use Modules\BI\Services\Connectors\CsvConnector;
use Modules\BI\Services\Connectors\RestApiConnector;
use Modules\BI\Services\DataSourceService;

uses(RefreshDatabase::class);

// ─── DataSourceService unit tests ─────────────────────────────────────────────

test('DataSourceService can create a data source with encrypted config', function () {
    $user    = User::factory()->create();
    $service = app(DataSourceService::class);

    $source = $service->create([
        'name'              => 'Test MySQL',
        'type'              => 'mysql',
        'connection_config' => ['host' => 'db.test', 'database' => 'erp', 'username' => 'ro', 'password' => 's3cret'],
    ], $user->id);

    expect($source->name)->toBe('Test MySQL');
    expect($source->type)->toBe('mysql');
    expect($source->created_by)->toBe($user->id);
});

test('connection_config is stored encrypted and not readable as plaintext', function () {
    $user    = User::factory()->create();
    $service = app(DataSourceService::class);

    $source = $service->create([
        'name'              => 'Encrypted Source',
        'type'              => 'postgresql',
        'connection_config' => ['host' => 'pg.test', 'password' => 'super-secret'],
    ], $user->id);

    $raw = DB::table('bi_data_sources')
        ->where('id', $source->id)
        ->value('connection_config');

    expect($raw)->not->toContain('super-secret');
});

test('DataSourceService resolves a MysqlConnector for mysql type', function () {
    $user    = User::factory()->create();
    $service = app(DataSourceService::class);

    $source = $service->create([
        'name' => 'MySQL DS',
        'type' => 'mysql',
        'connection_config' => ['host' => 'localhost', 'database' => 'test', 'username' => 'root', 'password' => ''],
    ], $user->id);

    $connector = $service->resolve($source);
    expect($connector)->toBeInstanceOf(\Modules\BI\Services\Connectors\MysqlConnector::class);
});

test('DataSourceService resolves a PostgresConnector for postgresql type', function () {
    $user    = User::factory()->create();
    $service = app(DataSourceService::class);

    $source = $service->create([
        'name' => 'PG DS',
        'type' => 'postgresql',
        'connection_config' => ['host' => 'localhost', 'database' => 'test', 'username' => 'postgres', 'password' => ''],
    ], $user->id);

    expect($service->resolve($source))->toBeInstanceOf(\Modules\BI\Services\Connectors\PostgresConnector::class);
});

test('DataSourceService resolves a RestApiConnector for rest_api type', function () {
    $user    = User::factory()->create();
    $service = app(DataSourceService::class);

    $source = $service->create([
        'name' => 'REST DS',
        'type' => 'rest_api',
        'connection_config' => ['base_url' => 'https://api.example.com'],
    ], $user->id);

    expect($service->resolve($source))->toBeInstanceOf(\Modules\BI\Services\Connectors\RestApiConnector::class);
});

test('DataSourceService resolves a CsvConnector for csv type', function () {
    $user    = User::factory()->create();
    $service = app(DataSourceService::class);

    $source = $service->create([
        'name' => 'CSV DS',
        'type' => 'csv',
        'connection_config' => ['file_path' => '/tmp/test.csv'],
    ], $user->id);

    expect($service->resolve($source))->toBeInstanceOf(\Modules\BI\Services\Connectors\CsvConnector::class);
});

test('DataSourceService resolves a GoogleSheetsConnector for google_sheets type', function () {
    $user    = User::factory()->create();
    $service = app(DataSourceService::class);

    $source = $service->create([
        'name' => 'GSheets DS',
        'type' => 'google_sheets',
        'connection_config' => ['spreadsheet_id' => 'abc123', 'api_key' => 'key'],
    ], $user->id);

    expect($service->resolve($source))->toBeInstanceOf(\Modules\BI\Services\Connectors\GoogleSheetsConnector::class);
});

test('DataSourceService throws on unsupported connector type', function () {
    $user    = User::factory()->create();
    $service = app(DataSourceService::class);

    $source          = BiDataSource::create([
        'name'              => 'Unknown',
        'type'              => 'ftp',
        'connection_config' => [],
        'created_by'        => $user->id,
        'status'            => 'inactive',
    ]);

    expect(fn () => $service->resolve($source))->toThrow(\InvalidArgumentException::class);
});

test('CsvConnector fetchData reads a real CSV file', function () {
    $path = tempnam(sys_get_temp_dir(), 'csv_test_');
    file_put_contents($path, "id,name,amount\n1,Alice,100\n2,Bob,200\n");

    $connector = new CsvConnector(['file_path' => $path]);
    $result    = $connector->fetchData();

    expect($result['columns'])->toBe(['id', 'name', 'amount']);
    expect($result['rows'])->toHaveCount(2);
    expect($result['rows'][0]['name'])->toBe('Alice');

    unlink($path);
});

test('CsvConnector getSchema returns column headers', function () {
    $path = tempnam(sys_get_temp_dir(), 'csv_schema_');
    file_put_contents($path, "sku,qty,price\na001,5,9.99\n");

    $connector = new CsvConnector(['file_path' => $path]);
    $schema    = $connector->getSchema();

    expect($schema[0]['table'])->toBe('csv');
    expect(array_column($schema[0]['columns'], 'name'))->toBe(['sku', 'qty', 'price']);

    unlink($path);
});

test('DataSourceService supportedTypes lists all five connector types', function () {
    $service = app(DataSourceService::class);
    $types   = $service->supportedTypes();
    $typeIds = array_column($types, 'type');

    expect($typeIds)->toContain('mysql')
        ->toContain('postgresql')
        ->toContain('rest_api')
        ->toContain('csv')
        ->toContain('google_sheets');
});

// ─── HTTP API tests ────────────────────────────────────────────────────────────

test('GET /bi/data-sources/types returns supported connector types', function () {
    actingAsUser('manager');

    $this->getJson('/api/v1/bi/data-sources/types')
        ->assertStatus(200)
        ->assertJsonFragment(['type' => 'mysql'])
        ->assertJsonFragment(['type' => 'csv']);
});

test('POST /bi/data-sources creates a data source', function () {
    actingAsUser('manager');

    $this->postJson('/api/v1/bi/data-sources', [
        'name'              => 'External MySQL',
        'type'              => 'mysql',
        'connection_config' => ['host' => 'db.example.com', 'database' => 'prod', 'username' => 'ro', 'password' => 'x'],
    ])->assertStatus(201)->assertJsonPath('name', 'External MySQL');
});
