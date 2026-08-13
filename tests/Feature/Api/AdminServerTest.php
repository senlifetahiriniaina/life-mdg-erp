<?php
declare(strict_types=1);
use App\Models\Admin\ServerConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
uses(RefreshDatabase::class);

it('admin can list servers', function () {
    actingAsUser('admin');
    ServerConfig::factory()->count(3)->create();

    $this->getJson('/api/v1/admin/servers')->assertOk();
});
it('admin can create a server', function () {
    actingAsUser('admin');

    $this->postJson('/api/v1/admin/servers', [
        'name'          => 'Prod GCP',
        'provider'      => 'gcp',
        'region'        => 'eu-west1',
        'instance_type' => 'e2-standard-2',
        'ip_address'    => '1.2.3.4',
    ])->assertCreated()->assertJsonFragment(['name' => 'Prod GCP', 'provider' => 'gcp']);
});
it('admin can ping a server', function () {
    actingAsUser('admin');
    $server = ServerConfig::factory()->create();

    $this->postJson("/api/v1/admin/servers/{$server->id}/ping")->assertOk();
});
it('admin can get server metrics', function () {
    actingAsUser('admin');
    $server = ServerConfig::factory()->create();

    $this->getJson("/api/v1/admin/servers/{$server->id}/metrics")->assertOk();
});
it('admin can delete a server', function () {
    actingAsUser('admin');
    $server = ServerConfig::factory()->create();

    $this->deleteJson("/api/v1/admin/servers/{$server->id}")->assertOk();
    $this->assertModelMissing($server);
});
