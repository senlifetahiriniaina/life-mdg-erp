<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Accounting\Services\SupplierDebtService;

/**
 * Chantier 32 (volet A3) — dettes fournisseurs, calculées en direct sur les
 * vraies factures fournisseur (`Invoice`, `type='bill'`). Lecture seule,
 * pas de Policy dédiée — même précédent que RatioController (aucun
 * enregistrement mutable à gate individuellement, le rôle de route suffit).
 */
class AgedPayablesController extends Controller
{
    public function __construct(private readonly SupplierDebtService $service) {}

    /** GET /supplier-debt/aged-payables */
    public function index(): JsonResponse
    {
        return response()->json($this->service->agedPayables());
    }
}
