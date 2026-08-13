<?php

declare(strict_types=1);

namespace Modules\Setup\Services;

/**
 * Stub service.
 *
 * The Setup module's routes/controllers reference this service, but a full
 * implementation was never written. These methods are placeholders so the
 * module boots and its routes register; they return inert results and must be
 * implemented before the corresponding endpoints are used in production.
 */
class ModuleManagerService
{
    public function activate(...$args): array
    {
        return ['implemented' => false, 'message' => 'ModuleManagerService::activate is not yet implemented.'];
    }
    public function bulkActivate(...$args): array
    {
        return ['implemented' => false, 'message' => 'ModuleManagerService::bulkActivate is not yet implemented.'];
    }
    public function deactivate(...$args): array
    {
        return ['implemented' => false, 'message' => 'ModuleManagerService::deactivate is not yet implemented.'];
    }
    public function getAll(...$args): array
    {
        return ['implemented' => false, 'message' => 'ModuleManagerService::getAll is not yet implemented.'];
    }
}
