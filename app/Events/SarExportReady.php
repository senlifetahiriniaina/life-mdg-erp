<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a GDPR Subject Access Request export has been written to disk.
 *
 * Not broadcast — consumed only by server-side listeners (e.g. email notification).
 */
class SarExportReady
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User   $user,
        public readonly string $path,
    ) {}
}
