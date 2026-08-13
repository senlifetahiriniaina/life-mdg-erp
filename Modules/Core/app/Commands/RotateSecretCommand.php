<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use Illuminate\Console\Command;
use Modules\Core\Services\SecretsService;

/**
 * RotateSecretCommand: Rotate a specific secret via CLI
 *
 * Usage:
 *   php artisan secrets:rotate my_api_key
 *   php artisan secrets:rotate my_api_key --new-value=sk_newvalue123
 */
class RotateSecretCommand extends Command
{
    protected $signature = 'secrets:rotate {name : Secret name} {--new-value= : New secret value (auto-generated if not provided)}';

    protected $description = 'Rotate a specific secret';

    private SecretsService $secretsService;

    public function __construct(SecretsService $secretsService)
    {
        parent::__construct();
        $this->secretsService = $secretsService;
    }

    public function handle(): int
    {
        try {
            $name = $this->argument('name');
            $newValue = $this->option('new-value');

            $this->info("Rotating secret: {$name}");

            // Show progress
            $bar = $this->output->createProgressBar(3);
            $bar->start();

            // Step 1: Load current secret
            $bar->advance();
            $this->info("\n[1/3] Loading secret...");

            // Step 2: Perform rotation
            $bar->advance();
            $this->info("[2/3] Performing rotation...");
            $secret = $this->secretsService->rotateSecret($name, $newValue);

            // Step 3: Verify rotation
            $bar->advance();
            $this->info("[3/3] Verifying rotation...");
            $bar->finish();

            // Display result
            $this->newLine();
            $this->info('Secret rotated successfully!');
            $this->table(
                ['ID', 'Name', 'Rotated At', 'Next Rotation', 'Key Version'],
                [[
                    $secret->id,
                    $secret->name,
                    $secret->rotated_at,
                    $secret->next_rotation,
                    $secret->key_version,
                ]]
            );

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to rotate secret: {$e->getMessage()}");
            return 1;
        }
    }
}
