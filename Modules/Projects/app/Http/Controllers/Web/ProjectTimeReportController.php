<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ProjectTimeReportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Projects/TimeReport/Index');
    }
}
