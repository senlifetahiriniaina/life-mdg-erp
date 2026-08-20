<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Logistics\Models\CustomsDeclaration;

/**
 * @group Logistics - Customs Declarations
 *
 * Chantier 19 Lot 4: this whole controller — the real, live code path
 * Customs/Index.vue actually calls (routes/api.php's separate
 * `logistics/customs` prefix, served by CustomsRouteController, is a
 * different, unreachable-from-any-UI-page endpoint set, already documented
 * separately) — had zero company/tenant scoping of any kind: any
 * authenticated Logistics-module user of any company could list, view,
 * edit, delete, and submit every other company's customs declarations.
 * Confirmed empirically via a real cross-company HTTP request. Fixed by
 * scoping every method through the real `tenant_id` column on
 * logistics_customs_declarations (this table has no `company_id` column —
 * confirmed via Schema::getColumnListing, unlike lgx_delivery_routes/
 * lgx_vehicles, which genuinely do — so `tenant_id` populated from the
 * caller's real users.company_id is the correct boundary here, matching
 * the fix already applied to CustomsRouteController/CustomsService).
 */
class CustomsDeclarationController extends Controller
{
    private function tenantId(Request $request): int
    {
        return (int) ($request->user()?->company_id ?? 0);
    }

    public function index(Request $request): JsonResponse
    {
        $q = CustomsDeclaration::query()
            ->where('tenant_id', $this->tenantId($request))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->input('shipment_id'), fn ($q, $v) => $q->where('shipment_id', $v))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $q->items(),
            'meta' => [
                'total' => $q->total(),
                'per_page' => $q->perPage(),
                'current_page' => $q->currentPage(),
                'last_page' => $q->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipment_id' => 'required|integer|exists:logistics_shipments,id',
            'type' => 'nullable|in:export,import,transit',
            'hs_code' => ['nullable', 'string', 'regex:/^\d{4}(\.\d{2}(\.\d{2})?)?$/'],
            'item_description' => 'nullable|string',
            'quantity' => 'nullable|numeric|min:0',
            'declared_value' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'country_of_origin' => 'nullable|string|size:2',
            'declaration_number' => 'nullable|string|unique:logistics_customs_declarations,declaration_number',
            'country_export' => 'nullable|string|size:2',
            'country_import' => 'nullable|string|size:2',
            'incoterm' => 'nullable|in:EXW,FCA,CPT,CIP,DAP,DDP,FOB,CFR,CIF',
            'total_declared_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['tenant_id'] = $this->tenantId($request);
        $data['status'] = 'draft';

        $declaration = CustomsDeclaration::create($data);
        return response()->json(['data' => $declaration], 201);
    }

    /** Resolves a declaration scoped to the caller's own company — 404, not 403, on a cross-company id (matches the ScopesToProjectCompany precedent elsewhere in this app). */
    private function findOwned(Request $request, int $id): CustomsDeclaration
    {
        return CustomsDeclaration::where('tenant_id', $this->tenantId($request))->findOrFail($id);
    }

    public function show(Request $request, CustomsDeclaration $customsDeclaration): JsonResponse
    {
        $declaration = $this->findOwned($request, $customsDeclaration->id);

        return response()->json(['data' => $declaration]);
    }

    public function update(Request $request, CustomsDeclaration $customsDeclaration): JsonResponse
    {
        $declaration = $this->findOwned($request, $customsDeclaration->id);

        $data = $request->validate([
            'hs_code' => 'nullable|string',
            'item_description' => 'nullable|string',
            'declared_value' => 'nullable|numeric|min:0',
            'total_declared_value' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'notes' => 'nullable|string',
            'incoterm' => 'nullable|in:EXW,FCA,CPT,CIP,DAP,DDP,FOB,CFR,CIF',
        ]);

        $declaration->update($data);

        return response()->json(['data' => $declaration->fresh()]);
    }

    public function destroy(Request $request, CustomsDeclaration $customsDeclaration): JsonResponse
    {
        $declaration = $this->findOwned($request, $customsDeclaration->id);
        $declaration->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    public function submit(Request $request, CustomsDeclaration $customsDeclaration): JsonResponse
    {
        $declaration = $this->findOwned($request, $customsDeclaration->id);

        abort_if($declaration->status !== 'draft', 422, 'Only draft declarations can be submitted.');

        $declaration->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return response()->json($declaration->fresh());
    }
}
