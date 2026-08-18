<?php

declare(strict_types=1);

namespace Modules\Reporting\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Chantier 8 (Reporting): Modules/Reporting had no Web/ controller at all —
 * ReportsIndex.vue (quick-report tiles, report history table, OHADA
 * balance-sheet shortcut) was a real, fully-built page with zero route
 * anywhere in the app, and routes/web.php was a literal empty placeholder.
 *
 * All three actions are thin wrappers: every page self-fetches everything
 * it needs via the real reporting API, so no server props are required —
 * same precedent as Accounting's ConsolidationHierarchyWebController /
 * Helpdesk's SlaAutomationWebController.
 */
class ReportingWebController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Reporting/ReportsIndex');
    }

    public function create(): Response
    {
        return Inertia::render('Reporting/Create');
    }

    public function show(int $id): Response
    {
        return Inertia::render('Reporting/Show', ['reportId' => $id]);
    }
}
