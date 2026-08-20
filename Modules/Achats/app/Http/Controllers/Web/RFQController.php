<?php

namespace Modules\Achats\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\Achats\Models\RFQ;

class RFQController extends Controller
{
    public function index()
    {
        return Inertia::render('Achats/RFQs/Index');
    }

    public function create()
    {
        return Inertia::render('Achats/RFQs/Form');
    }

    public function show(RFQ $rfq)
    {
        // Chantier 19: no company check at all — a direct URL visit could
        // server-render another company's RFQ (and its quotes) into the
        // page payload regardless of any API-layer fix.
        abort_unless($rfq->company_id === request()->user()?->company_id, 404);

        return Inertia::render('Achats/RFQs/Show', [
            'rfq' => $rfq->load(['lines', 'quotes']),
        ]);
    }

    public function edit(RFQ $rfq)
    {
        abort_unless($rfq->company_id === request()->user()?->company_id, 404);

        return Inertia::render('Achats/RFQs/Form', [
            'rfq' => $rfq,
        ]);
    }
}
