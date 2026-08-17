<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ForecastWebController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('CRM/Forecast/Index');
    }
}
