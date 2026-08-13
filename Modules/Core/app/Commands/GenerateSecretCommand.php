<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use Illuminate\Console\Command;
use Modules\Core\Services\SecretsService;

/**
 * GenerateSecretCommand: Create a new secret via CLI
 *
 * Usage:
 *   php artisan secrets:generate
 *   php artisan secrets:generate --name=my_api_key --type=api_key --expires=90
 */
class GenerateSecretCommand extends Command
{
    protected $signature = 'secrets:generate {--name= : Secret name} {--type=api_key : Secret type} {--expires=90 : Days until expiration} {--tags= : Comma-separated tags} {--rotation=30 : Rotation interval in days}';

    protected $description = 'Generate a new secret';

    private SecretsService $secretsService;

    public function __construct(SecretsService $secretsService)
    {
        parent::__construct();
        $this->secretsService = $secretsService;
    }

    public function handle(): int
    {
        try {
            // Prompt for name if not provided
            $name = $this->option('name') ?: $this->ask('Secret name');

            if (empty($name)) {
                $this->error('Secret name is required');
                return 1;
            }

            // Prompt for type
            $type = $this->option('type') ?: $this->choice(
                'Secret type',
                ['api_key', 'oauth_token', 'database_credential', 'ssh_key', 'certificate'],
                0
            );

            // Prompt for value
            $value = $this->secret('Secret value (hidden)');

            if (empty($value)) {
                $this->error('Secret value is required');
                return 1;
            }

            // Parse tags
            $tags = null;
            if ($this->option('tags')) {
                $tags = array_map('trim', explode(',', $this->option('tags')));
            }

            // Calculate expiration
            $expiresIn = (int) $this->option('expires');
            $expiresAt = $expiresIn > 0 ? now()->addDays($expiresIn)->toDateTimeString() : null;

            // Create secret
            $secret = $this->secretsService->storeSecret(
                $name,
                $value,
                $type,
                [
                    'expires_at' => $expiresAt,
                    'tags' => $tags,
                    'rotation_interval' => (int) $this->option('rotation'),
                ]
            );

            // Display result
            $this->info('Secret created successfully!');
            $this->table(
                ['ID', 'Name', 'Type', 'Expires At', 'Created At'],
                [[
                    $secret->id,
                    $secret->name,
                    $secret->type,
                    $secret->expires_at,
                    $secret->created_at,
                ]]
            );

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to generate secret: {$e->getMessage()}");
            return 1;
        }
    }
}
