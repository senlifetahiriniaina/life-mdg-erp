<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveAnalyticsWebController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('HR/Leave/Analytics');
    }
}
