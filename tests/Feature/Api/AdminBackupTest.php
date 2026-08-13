<?php
declare(strict_types=1);
use App\Models\Admin\Backup;
use App\Models\Admin\BackupSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

it('admin can list backups', function () {
    $user = actingAsUser('employee');
    $user->assignRole('admin');
    Backup::factory()->count(2)->create();

    $this->getJson('/api/v1/admin/backups')
        ->assertOk()
        ->assertJsonStructure(['data', 'total']);
});

it('admin can trigger a database backup', function () {
    $user = actingAsUser('employee');
    $user->assignRole('admin');

    $this->postJson('/api/v1/admin/backups', [
        'type' => 'database',
        'storage_driver' => 'local',
        'notes' => 'Test backup',
    ])
        ->assertCreated()
        ->assertJsonPath('status', 'completed');
});

it('admin can list backup schedules', function () {
    $user = actingAsUser('employee');
    $user->assignRole('admin');
    BackupSchedule::factory()->count(2)->create();

    $this->getJson('/api/v1/admin/backup-schedules')
        ->assertOk()
        ->assertJsonCount(2);
});

it('admin can create a backup schedule', function () {
    $user = actingAsUser('employee');
    $user->assignRole('admin');

    $this->postJson('/api/v1/admin/backup-schedules', [
        'type' => 'database',
        'frequency' => 'daily',
        'time_of_day' => '02:00',
        'storage_driver' => 'local',
    ])
        ->assertCreated()
        ->assertJsonPath('frequency', 'daily');
});
it('admin can delete a backup', function () {
    actingAsUser('admin');
    $backup = Backup::factory()->create();
    $this->deleteJson("/api/v1/admin/backups/{$backup->id}")->assertOk();
    $this->assertModelMissing($backup);
});
