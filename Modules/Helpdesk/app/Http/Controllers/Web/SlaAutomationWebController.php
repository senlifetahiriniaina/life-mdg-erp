<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SlaAutomationWebController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Helpdesk/SlaAutomation/Index');
    }
}
