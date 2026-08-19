<?php

namespace App\Console\Commands;

use App\Services\Backup\SchemaSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Produces a single compressed archive (`.zip`) containing the raw data
 * dump (`data.sql.gz`) plus a `manifest.json` schema/migration fingerprint —
 * the counterpart `backup:restore` command uses that manifest to detect and
 * reconcile drift between the schema a backup was taken under and the
 * schema the codebase defines today (see SchemaSnapshotService).
 *
 * Previously produced a bare `.sql.gz` with no manifest and no way to
 * restore or reconcile it — and read none of `config('backup.*')` despite
 * that config file existing specifically for this command (confirmed dead
 * config, now actually wired: disk/retention here, --s3 remains as an
 * explicit CLI override of the configured disk).
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--s3 : Force upload to S3, overriding config(\'backup.disk\')}';
    protected $description = 'Create a compressed, restorable database backup (data + schema manifest)';

    public function handle(SchemaSnapshotService $snapshots): int
    {
        try {
            $this->info('Starting database backup...');

            $timestamp = now()->format('Y-m-d_H-i-s');
            $workDir = storage_path("backups/tmp_{$timestamp}");
            mkdir($workDir, 0755, true);

            $dataFile = "{$workDir}/data.sql.gz";
            $this->dumpData($dataFile);
            $this->info('Data dump created: ' . $this->formatBytes(filesize($dataFile)));

            $manifest = [
                'created_at' => now()->toIso8601String(),
                'db_driver' => DB::connection()->getDriverName(),
                'app_env' => config('app.env'),
                'git_commit' => $this->currentGitCommit(),
                'migrations' => DB::table('migrations')->orderBy('id')->pluck('migration')->all(),
                'schema' => $snapshots->capture(),
            ];
            file_put_contents("{$workDir}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $archiveName = "backup_{$timestamp}.zip";
            $archivePath = storage_path("backups/{$archiveName}");
            $this->compress($workDir, $archivePath);
            $this->info('Compressed archive: ' . $this->formatBytes(filesize($archivePath)));

            $disk = $this->option('s3') ? 's3' : config('backup.disk', 'local');
            $this->store($archivePath, $archiveName, $disk);

            $this->deleteDirectory($workDir);
            if ($disk !== 'local') {
                unlink($archivePath);
            }

            Log::channel('backup')->info('Database backup completed', [
                'filename' => $archiveName,
                'disk' => $disk,
                'tables' => count($manifest['schema']),
                'migrations' => count($manifest['migrations']),
            ]);

            $this->info("✅ Backup completed successfully! ({$archiveName})");
            return 0;
        } catch (\Throwable $e) {
            $this->error("❌ Backup failed: {$e->getMessage()}");
            Log::channel('backup')->error('Database backup failed', [
                'error' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ]);
            return 1;
        }
    }

    private function dumpData(string $outputPath): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $sqlitePath = DB::connection()->getDatabaseName();
            file_put_contents($outputPath, gzencode(file_get_contents($sqlitePath), 9));
            return;
        }

        $command = $this->buildMysqlDumpCommand($outputPath);
        $result = Process::timeout(3600)->run($command);

        if (! $result->successful()) {
            throw new \Exception("mysqldump failed: {$result->errorOutput()}");
        }
    }

    private function buildMysqlDumpCommand(string $outputPath): string
    {
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port', 3306);
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        return sprintf(
            'mysqldump -h %s -P %d -u %s -p%s --single-transaction --quick --lock-tables=false %s | gzip > %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($outputPath)
        );
    }

    private function compress(string $workDir, string $archivePath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Unable to create archive at {$archivePath}");
        }

        foreach (['data.sql.gz', 'manifest.json'] as $file) {
            $zip->addFile("{$workDir}/{$file}", $file);
        }

        $zip->close();
    }

    private function store(string $archivePath, string $archiveName, string $disk): void
    {
        if ($disk === 'local') {
            // $archivePath is already storage_path("backups/{$archiveName}") — the
            // exact location backup:restore/backup:cleanup look for backups at
            // (both use a raw storage_path('backups/...') lookup, not the
            // Storage 'local' disk, whose root in Laravel 12 is
            // storage_path('app/private') — a different directory). Nothing to
            // copy: this is already the final resting place for a local backup.
            $this->info("Backup stored locally at: {$archivePath}");
            return;
        }

        $s3Path = trim(config('backup.s3.prefix', 'backups/daily'), '/') . "/{$archiveName}";
        $this->info("Uploading to S3: {$s3Path}");

        Storage::disk('s3')->put($s3Path, file_get_contents($archivePath), [
            'ServerSideEncryption' => config('backup.s3.encryption', 'AES256'),
            'StorageClass' => config('backup.s3.storage_class', 'STANDARD_IA'),
            'Metadata' => [
                'backup-type' => 'daily',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

        $this->info("✅ Successfully uploaded to S3: {$s3Path}");
    }

    private function currentGitCommit(): ?string
    {
        $result = Process::run('git rev-parse HEAD');
        return $result->successful() ? trim($result->output()) : null;
    }

    private function deleteDirectory(string $dir): void
    {
        foreach (glob("{$dir}/*") as $file) {
            unlink($file);
        }
        rmdir($dir);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $bytes;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }
}
