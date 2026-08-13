<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

/**
 * Stub service.
 *
 * The Accounting module's routes/controllers reference this service, but a full
 * implementation was never written. These methods are placeholders so the
 * module boots and its routes register; they return inert results and must be
 * implemented before the corresponding endpoints are used in production.
 */
class ScenarioPlanningService
{
    public function approveScenario(...$args): array
    {
        return ['implemented' => false, 'message' => 'ScenarioPlanningService::approveScenario is not yet implemented.'];
    }
    public function calculateImpact(...$args): array
    {
        return ['implemented' => false, 'message' => 'ScenarioPlanningService::calculateImpact is not yet implemented.'];
    }
    public function compareScenarios(...$args): array
    {
        return ['implemented' => false, 'message' => 'ScenarioPlanningService::compareScenarios is not yet implemented.'];
    }
    public function createScenario(...$args): array
    {
        return ['implemented' => false, 'message' => 'ScenarioPlanningService::createScenario is not yet implemented.'];
    }
    public function runSimulation(...$args): array
    {
        return ['implemented' => false, 'message' => 'ScenarioPlanningService::runSimulation is not yet implemented.'];
    }
    public function sensitivityAnalysis(...$args): array
    {
        return ['implemented' => false, 'message' => 'ScenarioPlanningService::sensitivityAnalysis is not yet implemented.'];
    }
}
