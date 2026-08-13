<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use Illuminate\Console\Command;
use Modules\Core\Models\Secret;
use Modules\Core\Services\SecretRotationManager;

/**
 * RotateDueSecretsCommand: Rotate all secrets due for rotation
 *
 * Usage:
 *   php artisan secrets:rotate-due
 *   php artisan secrets:rotate-due --dry-run
 */
class RotateDueSecretsCommand extends Command
{
    protected $signature = 'secrets:rotate-due {--dry-run : Show what would be rotated without making changes}';

    protected $description = 'Rotate all secrets due for rotation';

    private SecretRotationManager $rotationManager;

    public function __construct(SecretRotationManager $rotationManager)
    {
        parent::__construct();
        $this->rotationManager = $rotationManager;
    }

    public function handle(): int
    {
        try {
            $dryRun = $this->option('dry-run');

            $this->info('Checking for secrets due for rotation...');

            // Get all due secrets
            $upcomingRotations = $this->rotationManager->getUpcomingRotations(1); // Due within 1 day

            if ($upcomingRotations->isEmpty()) {
                $this->info('No secrets due for rotation');
                return 0;
            }

            $this->info("Found {$upcomingRotations->count()} secret(s) due for rotation");

            if ($dryRun) {
                $this->warn('DRY RUN MODE - No changes will be made');
            }

            // Display table
            $this->table(
                ['Name', 'Type', 'Due Date', 'Days Overdue'],
                $upcomingRotations->map(function ($rotation) {
                    $daysOverdue = now()->diffInDays($rotation['next_rotation_at']);
                    return [
                        $rotation['name'],
                        $rotation['type'],
                        $rotation['next_rotation_at'],
                        $daysOverdue > 0 ? "+{$daysOverdue}" : '0',
                    ];
                })
            );

            if ($dryRun) {
                $this->info('Dry run completed - no secrets were rotated');
                return 0;
            }

            // Rotate each secret
            $rotated = 0;
            $failed = 0;

            $bar = $this->output->createProgressBar($upcomingRotations->count());
            $bar->start();

            foreach ($upcomingRotations as $rotation) {
                try {
                    $this->rotationManager->executeRotation($rotation['name']);
                    $rotated++;
                    $bar->advance();
                } catch (\Exception $e) {
                    $failed++;
                    $this->error("\nFailed to rotate {$rotation['name']}: {$e->getMessage()}");
                    $bar->advance();
                }
            }

            $bar->finish();
            $this->newLine();

            // Summary
            $this->info("Rotation completed:");
            $this->info("  Successful: {$rotated}");
            if ($failed > 0) {
                $this->warn("  Failed: {$failed}");
            }

            return $failed > 0 ? 1 : 0;
        } catch (\Exception $e) {
            $this->error("Failed to rotate due secrets: {$e->getMessage()}");
            return 1;
        }
    }
}
