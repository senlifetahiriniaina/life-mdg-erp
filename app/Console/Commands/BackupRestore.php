<?php

namespace App\Console\Commands;

use App\Services\Backup\SchemaSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Restores a `backup:database`-produced `.zip` archive, then reconciles any
 * drift between the schema the backup's manifest.json recorded and the
 * schema the current codebase's migrations define — the case the backup
 * format exists for: the app has moved on (new columns/tables) since the
 * backup was taken, and a plain data restore would leave those new columns
 * either missing or NULL on every pre-existing row.
 *
 * Sequence: restore raw data as-is → run `migrate --force` (creates any new
 * tables/columns the codebase now defines, applying the DB's own defaults
 * where a migration declared one) → diff the manifest's schema snapshot
 * against the post-migrate live schema → for any newly-added column with
 * no DB-level default (so the DB engine had nothing to backfill existing
 * rows with), apply a type-appropriate default via SchemaSnapshotService
 * and record exactly what was changed in a reconciliation report.
 */
class BackupRestore extends Command
{
    protected $signature = 'backup:restore {file : Local path, storage/backups filename, or S3 key of a backup:database archive} {--force : Skip the confirmation prompt}';
    protected $description = 'Restore a compressed backup and reconcile any schema drift since it was taken';

    public function handle(SchemaSnapshotService $snapshots): int
    {
        $archivePath = $this->resolveArchive($this->argument('file'));
        if ($archivePath === null) {
            $this->error("Backup archive not found: {$this->argument('file')}");
            return 1;
        }

        if (! $this->option('force') && ! $this->confirm('This will overwrite the current database with the backup\'s data. Continue?')) {
            $this->warn('Restore cancelled.');
            return 1;
        }

        $workDir = storage_path('backups/tmp_restore_' . now()->format('Y-m-d_H-i-s'));
        mkdir($workDir, 0755, true);

        try {
            $this->extract($archivePath, $workDir);

            $manifest = json_decode(file_get_contents("{$workDir}/manifest.json"), true);
            if ($manifest === null) {
                throw new \Exception('manifest.json is missing or invalid in this archive.');
            }

            $this->info("Backup taken: {$manifest['created_at']} (driver: {$manifest['db_driver']})");

            $this->restoreData("{$workDir}/data.sql.gz");
            $this->info('Data restored.');

            $this->info('Running migrations to bring the schema up to the current codebase...');
            $migrateExit = Artisan::call('migrate', ['--force' => true]);
            $this->line(Artisan::output());

            if ($migrateExit !== 0) {
                $this->error('Migrations failed after restore — the database may be in a partially-migrated state. See the output above; this needs manual intervention rather than an automatic default value.');
                return 1;
            }

            $report = $this->reconcile($snapshots, $manifest['schema'] ?? []);
            $this->printReport($report);
            $this->saveReport($report);

            Log::channel('backup')->info('Database restore completed', [
                'archive' => basename($archivePath),
                'backup_created_at' => $manifest['created_at'],
                'new_tables' => count($report['new_tables']),
                'new_columns_backfilled' => array_sum(array_map('count', $report['backfilled'])),
            ]);

            $this->info('✅ Restore completed successfully!');
            return 0;
        } catch (\Throwable $e) {
            $this->error("❌ Restore failed: {$e->getMessage()}");
            Log::channel('backup')->error('Database restore failed', ['error' => $e->getMessage()]);
            return 1;
        } finally {
            $this->deleteDirectory($workDir);
        }
    }

    private function resolveArchive(string $file): ?string
    {
        if (is_file($file)) {
            return $file;
        }

        $localCandidate = storage_path("backups/{$file}");
        if (is_file($localCandidate)) {
            return $localCandidate;
        }

        $s3Path = trim(config('backup.s3.prefix', 'backups/daily'), '/') . "/{$file}";
        if (Storage::disk('s3')->exists($s3Path)) {
            $downloaded = storage_path('backups/' . basename($file));
            file_put_contents($downloaded, Storage::disk('s3')->get($s3Path));
            return $downloaded;
        }

        return null;
    }

    private function extract(string $archivePath, string $workDir): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new \Exception("Unable to open archive: {$archivePath}");
        }
        $zip->extractTo($workDir);
        $zip->close();
    }

    private function restoreData(string $dataFile): void
    {
        $driver = DB::connection()->getDriverName();
        $raw = gzdecode(file_get_contents($dataFile));

        if ($driver === 'sqlite') {
            $sqlitePath = DB::connection()->getDatabaseName();
            DB::disconnect();
            file_put_contents($sqlitePath, $raw);
            DB::reconnect();
            return;
        }

        $sqlFile = tempnam(sys_get_temp_dir(), 'restore_') . '.sql';
        file_put_contents($sqlFile, $raw);

        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port', 3306);
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $command = sprintf(
            'mysql -h %s -P %d -u %s -p%s %s < %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($sqlFile)
        );

        $result = Process::timeout(3600)->run($command);
        unlink($sqlFile);

        if (! $result->successful()) {
            throw new \Exception("mysql restore failed: {$result->errorOutput()}");
        }

        DB::reconnect();
    }

    /**
     * @param  array<string, array<string, array{type: string, nullable: bool, default: mixed}>> $oldSchema
     */
    private function reconcile(SchemaSnapshotService $snapshots, array $oldSchema): array
    {
        $newSchema = $snapshots->capture();
        $diff = $snapshots->diff($oldSchema, $newSchema);
        $backfilled = $snapshots->backfillNewColumns($diff['new_columns']);

        return [
            'new_tables' => $diff['new_tables'],
            'dropped_tables' => $diff['dropped_tables'],
            'new_columns' => $diff['new_columns'],
            'dropped_columns' => $diff['dropped_columns'],
            'backfilled' => $backfilled,
        ];
    }

    private function printReport(array $report): void
    {
        $this->newLine();
        $this->info('── Reconciliation report ──────────────────────────');

        if ($report['new_tables'] !== []) {
            $this->line('New tables created empty by this codebase\'s migrations: ' . implode(', ', $report['new_tables']));
        }
        if ($report['dropped_tables'] !== []) {
            $this->warn('Tables the backup had, which no longer exist: ' . implode(', ', $report['dropped_tables']));
        }
        foreach ($report['dropped_columns'] as $table => $columns) {
            $this->warn("{$table}: columns dropped by later migrations (data discarded): " . implode(', ', $columns));
        }
        if ($report['backfilled'] === [] && $report['new_tables'] === [] && $report['dropped_tables'] === [] && $report['dropped_columns'] === []) {
            $this->line('No schema drift detected — the backup already matched the current structure.');
        }
        foreach ($report['backfilled'] as $table => $columns) {
            foreach ($columns as $column => $value) {
                $this->line("{$table}.{$column}: new column, no DB default — backfilled existing rows with " . json_encode($value));
            }
        }

        $this->info('────────────────────────────────────────────────────');
    }

    private function saveReport(array $report): void
    {
        $path = 'backups/reports/restore_' . now()->format('Y-m-d_H-i-s') . '.json';
        Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        // The 'local' disk's root in Laravel 12 is storage_path('app/private'),
        // not storage_path('app') — report this path for real rather than a
        // stale storage/app/... guess (unlike the backup archive itself, this
        // report is never read back by another command, so the disk-root
        // mismatch that mattered for backup:database/backup:cleanup doesn't
        // apply here).
        $this->line('Report saved to storage/app/private/' . $path);
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (glob("{$dir}/*") as $file) {
            unlink($file);
        }
        rmdir($dir);
    }
}
