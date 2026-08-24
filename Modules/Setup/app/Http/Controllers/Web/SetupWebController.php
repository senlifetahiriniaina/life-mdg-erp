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

    /**
     * Chantier 32.10 (deep 14-layer audit): both methods below still read
     * `$request->user()->tenant_id ?? 'default'` — the well-documented
     * phantom `users.tenant_id` column (real DB column, never in
     * `User::$fillable`, never populated by any real registration path).
     * Every OTHER tenant-scoping site in this module was already fixed to
     * `company_id` across Chantiers 8.5sv/10/19-Lot-3 (see e.g.
     * `SetupController::tenantId()`'s own docblock for the full
     * investigation) — this one, the controller behind the two real
     * server-rendered onboarding pages (`/setup`, `/setup/wizard`), was
     * missed. Confirmed empirically: every company that ever visited
     * either page had its INITIAL page-load `wizardState`/`state` Inertia
     * prop silently collapse into one shared `'default'` bucket — Company
     * A's onboarding draft (company profile, module selection, workflow
     * config) was served to Company B on first load, even though the
     * underlying API endpoints (`SetupWizardController`) were already
     * correctly scoped by `company_id`. Fixed to match.
     */
    private function tenantId(Request $request): string
    {
        return (string) ($request->user()->company_id ?? 0);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Setup/SetupIndex', [
            'wizardState' => $this->wizardService->getState($this->tenantId($request)),
        ]);
    }

    /**
     * Server-side initial state avoids an extra client round-trip to
     * GET /api/v1/setup/wizard/state on load.
     */
    public function wizard(Request $request): Response
    {
        return Inertia::render('Setup/SetupWizard', [
            'state' => $this->wizardService->getState($this->tenantId($request)),
        ]);
    }
}
