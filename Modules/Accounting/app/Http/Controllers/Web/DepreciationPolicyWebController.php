<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DepreciationPolicyWebController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Accounting/DepreciationPolicies/Index');
    }
}
