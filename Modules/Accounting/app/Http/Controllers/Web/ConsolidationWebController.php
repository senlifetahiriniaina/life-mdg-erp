<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Models\Company;

/**
 * Web pages for the Company-tree consolidation feature (multi-company
 * hierarchy, intercompany eliminations, consolidated reports). Distinct
 * from the newer ConsolidationGroup stack served at consolidation-hierarchies.
 */
class ConsolidationWebController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Accounting/Consolidation/Index');
    }

    public function create(): Response
    {
        return Inertia::render('Accounting/Consolidation/Create');
    }

    public function show(Company $company): Response
    {
        return Inertia::render('Accounting/Consolidation/Detail', [
            'companyId' => $company->id,
        ]);
    }
}
