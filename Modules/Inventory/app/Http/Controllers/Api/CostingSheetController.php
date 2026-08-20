<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Requests\StoreCostingSheetRequest;
use Modules\Inventory\Http\Requests\UpdateCostingSheetRequest;
use Modules\Inventory\Models\CostingSheet;
use Modules\Inventory\Services\CostingSheetService;

/**
 * Chantier 21 — nomenclature de coût (BOM devis chiffré). L'équipe
 * avant-vente construit une fiche de chiffrage (matière + accessoires +
 * main-d'œuvre + frais fixes) rattachée à une opportunité CRM, qui devient
 * ensuite la base d'un devis Sales une fois approuvée.
 */
class CostingSheetController extends Controller
{
    private const WITH = ['lines.productTemplate:id,name,code', 'lines.supplier:id,name', 'productTemplate:id,name,code'];

    public function __construct(private readonly CostingSheetService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CostingSheet::class);

        $sheets = CostingSheet::query()
            ->with('productTemplate:id,name,code')
            ->when($request->filled('status'), fn ($q) => $q->status($request->string('status')))
            ->when($request->filled('opportunity_id'), fn ($q) => $q->where('opportunity_id', $request->integer('opportunity_id')))
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($sheets);
    }

    public function show(CostingSheet $costingSheet): JsonResponse
    {
        $this->authorize('view', $costingSheet);

        return response()->json(['data' => $costingSheet->load(self::WITH)]);
    }

    public function store(StoreCostingSheetRequest $request): JsonResponse
    {
        $this->authorize('create', CostingSheet::class);

        $sheet = $this->service->create($request->validated(), $request->user()->id);

        return response()->json(['data' => $sheet->load(self::WITH)], 201);
    }

    public function update(UpdateCostingSheetRequest $request, CostingSheet $costingSheet): JsonResponse
    {
        $this->authorize('update', $costingSheet);

        $sheet = $this->service->update($costingSheet, $request->validated());

        return response()->json(['data' => $sheet->load(self::WITH)]);
    }

    public function destroy(CostingSheet $costingSheet): JsonResponse
    {
        $this->authorize('delete', $costingSheet);

        $costingSheet->delete();

        return response()->json(null, 204);
    }

    public function duplicate(Request $request, CostingSheet $costingSheet): JsonResponse
    {
        $this->authorize('create', CostingSheet::class);

        $revision = $this->service->duplicateAsRevision($costingSheet, $request->user()->id);

        return response()->json(['data' => $revision->load(self::WITH)], 201);
    }
}
