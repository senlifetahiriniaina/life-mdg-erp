<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Exercises the real backup:database → backup:restore round trip, including
 * the schema-drift/reconciliation path — the case Chantier 14 was built for:
 * the codebase gains a migration after a backup was taken, and restoring
 * that backup must bring the DB back up to the *current* schema, not just
 * replay stale bytes.
 *
 * Every artisan command runs as a genuinely separate `php artisan ...`
 * subprocess against a dedicated, file-backed sqlite database (DB_DATABASE
 * overridden per-call) rather than in-process against this suite's shared
 * `:memory:` connection (phpunit.xml). That's not just isolation for its own
 * sake: a full in-process `migrate` against a second named connection was
 * tried first and found to be unsafe here — Telescope's own migration
 * hardcodes `Schema::connection(env('DB_CONNECTION'))` regardless of
 * whichever connection is "current", so it always targets the same 'sqlite'
 * connection the rest of the suite already migrated once at boot, colliding
 * with an already-existing `telescope_entries` table. A real subprocess
 * sidesteps this entirely — each boots fresh, so 'sqlite' cleanly resolves
 * to *our* temp file every time, with zero shared state.
 */
it('restores a backup and backfills a column added by a migration since it was taken', function () {
    $tmpDb = storage_path('framework/testing/chantier14_backup_restore.sqlite');
    $backupDir = storage_path('backups');
    $reportsDir = storage_path('app/private/backups/reports');
    $driftMigrationPath = database_path('migrations/2099_01_01_000000_chantier14_add_status_to_drift_probe.php');

    @unlink($driftMigrationPath);
    @unlink($tmpDb);
    touch($tmpDb);
    File::deleteDirectory($backupDir);
    File::deleteDirectory($reportsDir);

    $env = ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $tmpDb, 'BACKUP_DISK' => 'local'];
    $artisan = fn (string $cmd) => Process::path(base_path())->env($env)->timeout(180)->run("php artisan {$cmd}");
    $pdo = fn () => new PDO('sqlite:'.$tmpDb);

    try {
        $migrateV1 = $artisan('migrate --force');
        expect($migrateV1->successful())->toBeTrue($migrateV1->errorOutput());

        $db = $pdo();
        $db->exec('CREATE TABLE chantier14_drift_probe (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR NOT NULL, created_at DATETIME, updated_at DATETIME)');
        $db->exec("INSERT INTO chantier14_drift_probe (name, created_at, updated_at) VALUES ('alpha', datetime('now'), datetime('now'))");
        $db->exec("INSERT INTO chantier14_drift_probe (name, created_at, updated_at) VALUES ('beta', datetime('now'), datetime('now'))");
        $db = null;

        $backup = $artisan('backup:database');
        expect($backup->successful())->toBeTrue($backup->errorOutput());
        preg_match('/backup_[\d_-]+\.zip/', $backup->output(), $matches);
        $archiveName = $matches[0] ?? null;
        expect($archiveName)->not->toBeNull();
        expect("{$backupDir}/{$archiveName}")->toBeFile();

        // Simulate the codebase moving on: a real migration, in the app's
        // real database/migrations/ path (so backup:restore's plain
        // `migrate --force` will actually discover it), adds a nullable
        // no-default column after the backup was taken.
        File::put($driftMigrationPath, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chantier14_drift_probe', function (Blueprint $table) {
            $table->string('status')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('chantier14_drift_probe', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
PHP
        );

        $migrateV2 = $artisan('migrate --force');
        expect($migrateV2->successful())->toBeTrue($migrateV2->errorOutput());

        $db = $pdo();
        $db->exec("INSERT INTO chantier14_drift_probe (name, status, created_at, updated_at) VALUES ('post-backup-row', 'should not survive restore', datetime('now'), datetime('now'))");
        expect((int) $db->query('SELECT COUNT(*) FROM chantier14_drift_probe')->fetchColumn())->toBe(3);
        $db = null;

        $restore = $artisan("backup:restore {$archiveName} --force");
        expect($restore->successful())->toBeTrue($restore->errorOutput());
        $restoreOutput = $restore->output();

        $db = $pdo();
        $rows = $db->query('SELECT * FROM chantier14_drift_probe ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $db = null;

        // The 3rd row (inserted after the backup) did not survive — restore
        // replayed the backup's own data, not a merge with live data.
        expect($rows)->toHaveCount(2);
        expect(array_column($rows, 'name'))->toBe(['alpha', 'beta']);

        // The drift migration ran again post-restore (it's a real migration
        // in a scanned path), so the column exists — and since it has no
        // DB-level default, SchemaSnapshotService backfilled it rather than
        // leaving the restored rows NULL.
        foreach ($rows as $row) {
            expect(array_key_exists('status', $row))->toBeTrue();
            expect($row['status'])->toBe('');
        }

        expect($restoreOutput)->toContain('chantier14_drift_probe.status');

        $reportFiles = glob("{$reportsDir}/restore_*.json");
        expect($reportFiles)->not->toBeEmpty();
        $report = json_decode(file_get_contents(end($reportFiles)), true);
        expect($report['backfilled']['chantier14_drift_probe']['status'] ?? null)->toBe('');
    } finally {
        @unlink($driftMigrationPath);
        @unlink($tmpDb);
        File::deleteDirectory($backupDir);
        File::deleteDirectory(storage_path('app/private/backups'));
    }
});
