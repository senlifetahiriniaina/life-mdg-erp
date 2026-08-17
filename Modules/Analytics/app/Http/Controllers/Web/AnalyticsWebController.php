<?php

declare(strict_types=1);

namespace Modules\Analytics\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsWebController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Analytics/Index');
    }
}
