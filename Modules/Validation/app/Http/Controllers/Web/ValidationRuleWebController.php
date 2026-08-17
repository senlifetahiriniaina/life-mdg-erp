<?php

namespace Modules\Validation\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class ValidationRuleWebController extends Controller
{
    public function index()
    {
        return Inertia::render('Validation/ValidationRules/Index');
    }
}
