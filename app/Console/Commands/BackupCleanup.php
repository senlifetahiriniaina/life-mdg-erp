<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupCleanup extends Command
{
    protected $signature = 'backup:cleanup {--days=30 : Number of days to retain backups}';
    protected $description = 'Clean up old database backups';

    public function handle(): int
    {
        try {
            $days = (int) $this->option('days');
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

        $files = glob("{$backupDir}/*.sql.gz");
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
