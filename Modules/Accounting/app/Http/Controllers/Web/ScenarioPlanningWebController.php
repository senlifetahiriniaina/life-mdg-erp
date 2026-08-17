<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ScenarioPlanningWebController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Accounting/ScenarioPlanning/Index');
    }
}
