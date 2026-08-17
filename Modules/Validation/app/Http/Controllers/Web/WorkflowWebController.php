<?php

namespace Modules\Validation\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\Validation\Models\ApprovalWorkflow;

class WorkflowWebController extends Controller
{
    public function index()
    {
        return Inertia::render('Validation/Workflows/Index');
    }

    public function create()
    {
        return Inertia::render('Validation/Workflows/Builder');
    }

    public function builder(ApprovalWorkflow $workflow)
    {
        return Inertia::render('Validation/Workflows/Builder');
    }
}
