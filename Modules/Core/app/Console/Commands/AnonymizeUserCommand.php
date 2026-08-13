<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Jobs\AnonymizeUserJob;

class AnonymizeUserCommand extends Command
{
    protected $signature = 'gdpr:anonymize-user {userId : The numeric ID of the user to anonymise}';

    protected $description = 'Anonymise all personal data for a user (GDPR art. 17 — right to erasure)';

    public function handle(): int
    {
        $userId = (int) $this->argument('userId');

        if ($userId <= 0) {
            $this->error('userId must be a positive integer.');

            return self::FAILURE;
        }

        AnonymizeUserJob::dispatch($userId);

        $this->info("Anonymisation job dispatched for user #{$userId}.");

        return self::SUCCESS;
    }
}
