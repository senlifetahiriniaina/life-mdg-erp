<?php

declare(strict_types=1);

namespace Modules\Setup\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Setup\Services\SetupWizardService;

class SetupWebController extends Controller
{
    public function __construct(private readonly SetupWizardService $wizardService) {}

    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id ?? 'default';

        return Inertia::render('Setup/SetupIndex', [
            'wizardState' => $this->wizardService->getState($tenantId),
        ]);
    }

    /**
     * Server-side initial state avoids an extra client round-trip to
     * GET /api/v1/setup/wizard/state on load.
     */
    public function wizard(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id ?? 'default';

        return Inertia::render('Setup/SetupWizard', [
            'state' => $this->wizardService->getState($tenantId),
        ]);
    }
}
