<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Helpdesk\Models\EscalationRule;
use Modules\Helpdesk\Models\HelpdeskSlaPolicy;

class EscalationWebController extends Controller
{
    public function index(): Response
    {
        $slaPolicies = HelpdeskSlaPolicy::orderBy('priority')->get();
        $escalationRules = EscalationRule::with('slaPolicy:id,name')->orderBy('priority')->get();

        return Inertia::render('Helpdesk/Escalation/Index', [
            'slaPolicies' => $slaPolicies,
            'escalationRules' => $escalationRules,
        ]);
    }
}
