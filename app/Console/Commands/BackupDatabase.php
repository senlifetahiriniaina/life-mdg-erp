<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--s3 : Upload to S3 instead of local storage}';
    protected $description = 'Backup database to local or S3 storage';

    public function handle(): int
    {
        try {
            $this->info('Starting database backup...');

            $timestamp = now()->format('Y-m-d_H-i-s');
            $backupFile = "database_backup_{$timestamp}.sql.gz";
            $tempPath = storage_path("backups/{$backupFile}");

            // Ensure backup directory exists
            if (!is_dir(storage_path('backups'))) {
                mkdir(storage_path('backups'), 0755, true);
            }

            // Execute mysqldump
            $command = $this->buildMysqlDumpCommand($tempPath);
            $this->info("Executing: {$command}");

            $result = Process::timeout(3600)->run($command);

            if (!$result->successful()) {
                throw new \Exception("Mysqldump failed: {$result->errorOutput()}");
            }

            $fileSize = filesize($tempPath);
            $this->info("Database backed up successfully. Size: " . $this->formatBytes($fileSize));

            // Upload to S3 if requested
            if ($this->option('s3')) {
                $this->uploadToS3($tempPath, $backupFile);
            } else {
                // Keep in local storage
                $storagePath = "backups/{$backupFile}";
                Storage::disk('local')->putFileAs('backups', $tempPath, $backupFile);
                $this->info("Backup stored locally at: {$storagePath}");
            }

            // Cleanup temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            // Log backup completion
            Log::channel('backup')->info('Database backup completed', [
                'filename' => $backupFile,
                'size' => $fileSize,
                'timestamp' => $timestamp,
                'location' => $this->option('s3') ? 's3' : 'local',
            ]);

            $this->info('✅ Backup completed successfully!');
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

    private function buildMysqlDumpCommand(string $outputPath): string
    {
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port', 3306);
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        // Build mysqldump command with compression
        $cmd = sprintf(
            'mysqldump -h %s -P %d -u %s -p%s --single-transaction --quick --lock-tables=false %s | gzip > %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($outputPath)
        );

        return $cmd;
    }

    private function uploadToS3(string $localPath, string $filename): void
    {
        $s3Path = "backups/daily/{$filename}";

        $this->info("Uploading to S3: {$s3Path}");

        try {
            // Read file and upload to S3
            $content = file_get_contents($localPath);
            Storage::disk('s3')->put($s3Path, $content, [
                'ServerSideEncryption' => 'AES256',
                'Metadata' => [
                    'backup-type' => 'daily',
                    'timestamp' => now()->toIso8601String(),
                    'database' => config('database.connections.mysql.database'),
                ],
            ]);

            $this->info("✅ Successfully uploaded to S3: {$s3Path}");
            Log::channel('backup')->info('S3 upload completed', ['path' => $s3Path]);
        } catch (\Throwable $e) {
            throw new \Exception("S3 upload failed: {$e->getMessage()}");
        }
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
