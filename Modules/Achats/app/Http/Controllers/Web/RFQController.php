<?php

namespace Modules\Achats\Http\Controllers\Web;

use Illuminate\Routing\Controller;
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
        return Inertia::render('Achats/RFQs/Show', [
            'rfq' => $rfq->load(['lines', 'quotes']),
        ]);
    }

    public function edit(RFQ $rfq)
    {
        return Inertia::render('Achats/RFQs/Form', [
            'rfq' => $rfq,
        ]);
    }
}
