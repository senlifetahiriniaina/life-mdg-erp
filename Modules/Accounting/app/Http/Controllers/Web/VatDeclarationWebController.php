<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Models\VatDeclaration;

class VatDeclarationWebController extends Controller
{
    public function index(): Response
    {
        $declarations = VatDeclaration::latest()->paginate(25);

        return Inertia::render('Accounting/VatDeclaration/Index', [
            'declarations' => $declarations,
        ]);
    }

    public function show(VatDeclaration $vatDeclaration): Response
    {
        return Inertia::render('Accounting/VatDeclaration/Show', [
            'declaration' => $vatDeclaration,
        ]);
    }
}
