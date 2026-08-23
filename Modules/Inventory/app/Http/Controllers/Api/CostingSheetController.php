<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Controllers\Api\Concerns\ScopesToCompany;
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
    // Chantier 32: authorize() (permission, 403) + assertSameCompany()
    // (per-record ownership, 404) as two separate calls, matching Achats'
    // real precedent — also used for companyId()/assertSupplierBelongsToCompany().
    use ScopesToCompany;

    private const WITH = ['lines.productTemplate:id,name,code', 'lines.supplier:id,name', 'productTemplate:id,name,code'];

    public function __construct(private readonly CostingSheetService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CostingSheet::class);

        $sheets = CostingSheet::query()
            ->where('company_id', $request->user()?->company_id)
            ->with('productTemplate:id,name,code')
            ->when($request->filled('status'), fn ($q) => $q->status($request->string('status')))
            ->when($request->filled('opportunity_id'), fn ($q) => $q->where('opportunity_id', $request->integer('opportunity_id')))
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($sheets);
    }

    public function show(Request $request, CostingSheet $costingSheet): JsonResponse
    {
        $this->authorize('view', $costingSheet);
        $this->assertSameCompany($request, $costingSheet);

        return response()->json(['data' => $costingSheet->load(self::WITH)]);
    }

    public function store(StoreCostingSheetRequest $request): JsonResponse
    {
        $this->authorize('create', CostingSheet::class);

        // Chantier 32: company_id is always server-derived from the caller,
        // never trusted from client input (StoreCostingSheetRequest doesn't
        // validate/accept it at all).
        $data = $request->validated();
        $data['company_id'] = $request->user()?->company_id;

        foreach ($data['lines'] ?? [] as $line) {
            $this->assertSupplierBelongsToCompany($request, $line['supplier_id'] ?? null);
        }

        $sheet = $this->service->create($data, $request->user()->id);

        return response()->json(['data' => $sheet->load(self::WITH)], 201);
    }

    public function update(UpdateCostingSheetRequest $request, CostingSheet $costingSheet): JsonResponse
    {
        $this->authorize('update', $costingSheet);
        $this->assertSameCompany($request, $costingSheet);

        $validated = $request->validated();
        foreach ($validated['lines'] ?? [] as $line) {
            $this->assertSupplierBelongsToCompany($request, $line['supplier_id'] ?? null);
        }

        $sheet = $this->service->update($costingSheet, $validated);

        return response()->json(['data' => $sheet->load(self::WITH)]);
    }

    public function destroy(Request $request, CostingSheet $costingSheet): JsonResponse
    {
        $this->authorize('delete', $costingSheet);
        $this->assertSameCompany($request, $costingSheet);

        $costingSheet->delete();

        return response()->json(null, 204);
    }

    public function duplicate(Request $request, CostingSheet $costingSheet): JsonResponse
    {
        // Chantier 32: 'create' alone doesn't check the SOURCE record's
        // company — a caller could otherwise duplicate-as-revision another
        // company's costing sheet by id. assertSameCompany() closes it,
        // matching this controller's own established authorize()+
        // assertSameCompany() pairing everywhere else.
        $this->authorize('view', $costingSheet);
        $this->assertSameCompany($request, $costingSheet);
        $this->authorize('create', CostingSheet::class);

        $revision = $this->service->duplicateAsRevision($costingSheet, $request->user()->id);

        return response()->json(['data' => $revision->load(self::WITH)], 201);
    }
}
