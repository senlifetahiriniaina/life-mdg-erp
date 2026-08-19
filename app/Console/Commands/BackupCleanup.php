<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupCleanup extends Command
{
    protected $signature = 'backup:cleanup {--days= : Number of days to retain backups (defaults to config(\'backup.retention_days\'))}';
    protected $description = 'Clean up old database backups';

    public function handle(): int
    {
        try {
            $days = (int) ($this->option('days') ?? config('backup.retention_days', 30));
            $cutoffDate = now()->subDays($days);

            $this->info("Cleaning up backups older than {$days} days ({$cutoffDate->toDateString()})...");

            // Cleanup local backups
            $this->cleanupLocalBackups($cutoffDate);

            // Cleanup S3 backups
            $this->cleanupS3Backups($cutoffDate);

            $this->info('✅ Backup cleanup completed!');
            return 0;
        } catch (\Throwable $e) {
            $this->error("❌ Cleanup failed: {$e->getMessage()}");
            Log::error('Backup cleanup failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }

    private function cleanupLocalBackups(\DateTime $cutoffDate): void
    {
        $backupDir = storage_path('backups');

        if (!is_dir($backupDir)) {
            $this->info('No local backup directory found');
            return;
        }

        // Chantier 14: BackupDatabase now produces .zip archives (data + schema
        // manifest) instead of bare .sql.gz — matches both patterns so backups
        // made before this change still get cleaned up on schedule.
        $files = array_merge(glob("{$backupDir}/*.zip"), glob("{$backupDir}/*.sql.gz"));
        $deletedCount = 0;

        foreach ($files as $file) {
            $fileTime = filemtime($file);

            if ($fileTime < $cutoffDate->timestamp) {
                $filename = basename($file);
                unlink($file);
                $this->line("  Deleted: {$filename}");
                $deletedCount++;
            }
        }

        $this->info("Cleaned up {$deletedCount} local backup files");
        Log::info('Local backup cleanup completed', ['deleted_count' => $deletedCount]);
    }

    private function cleanupS3Backups(\DateTime $cutoffDate): void
    {
        try {
            $s3Disk = Storage::disk('s3');
            $files = $s3Disk->listContents('backups/daily', true);

            $deletedCount = 0;

            foreach ($files as $file) {
                if ($file['type'] === 'file') {
                    $fileTime = strtotime($file['timestamp'] ?? $file['last_modified'] ?? 0);

                    if ($fileTime < $cutoffDate->timestamp) {
                        $s3Disk->delete($file['path']);
                        $this->line("  Deleted from S3: {$file['path']}");
                        $deletedCount++;
                    }
                }
            }

            $this->info("Cleaned up {$deletedCount} S3 backup files");
            Log::info('S3 backup cleanup completed', ['deleted_count' => $deletedCount]);
        } catch (\Throwable $e) {
            $this->warn("⚠️  S3 cleanup failed (S3 may not be configured): {$e->getMessage()}");
        }
    }
}
