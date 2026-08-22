<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Logistics\Models\CustomsDeclaration;
use Modules\Logistics\Models\DeliveryRoute;
use Modules\Logistics\Models\Vehicle;
use Modules\Logistics\Services\CarrierIntegrationService;
use Modules\Logistics\Services\CustomsService;
use Modules\Logistics\Services\RouteOptimizationService;

/**
 * @group Logistics Phase 47 — Customs Clearance + Route Optimization + African Carriers
 */
class CustomsRouteController extends Controller
{
    public function __construct(
        private readonly CustomsService $customs,
        private readonly RouteOptimizationService $routeOpt,
        private readonly CarrierIntegrationService $carrierInt,
    ) {}

    // ── CUSTOMS DECLARATIONS ────────────────────────────────────────────────

    /** GET /api/v1/logistics/customs */
    public function customsIndex(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 0;

        $declarations = CustomsDeclaration::where('tenant_id', $companyId)
            ->when($request->input('status'), fn($q, $v) => $q->where('status', $v))
            ->when($request->input('type'),   fn($q, $v) => $q->where('type', $v))
            ->when($request->input('search'), fn($q, $v) =>
                $q->where('reference', 'like', "%{$v}%")
                  ->orWhere('customs_broker', 'like', "%{$v}%")
            )
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($declarations);
    }

    /** POST /api/v1/logistics/customs */
    public function customsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipment_id'               => 'nullable|integer',
            'type'                      => 'required|in:import,export,transit',
            'country_of_origin'         => 'required|string|size:2',
            'country_of_destination'    => 'required|string|size:2',
            'port_of_entry'             => 'nullable|string|max:100',
            'incoterm'                  => 'nullable|string|max:10',
            'total_value'               => 'nullable|numeric|min:0',
            'currency'                  => 'nullable|string|size:3',
            'customs_broker'            => 'nullable|string|max:200',
            'notes'                     => 'nullable|string',
            'documents'                 => 'nullable|array',
            'items'                     => 'nullable|array',
            'items.*.description'       => 'required_with:items|string|max:500',
            'items.*.hs_code'           => 'nullable|string|max:10',
            'items.*.qty'               => 'nullable|numeric|min:0',
            'items.*.unit'              => 'nullable|string|max:20',
            'items.*.unit_value'        => 'nullable|numeric|min:0',
            'items.*.total_value'       => 'nullable|numeric|min:0',
            'items.*.country_of_origin' => 'nullable|string|size:2',
            'items.*.weight_kg'         => 'nullable|numeric|min:0',
        ]);

        $data['tenant_id'] = $request->user()->company_id ?? 0;

        $declaration = $this->customs->createDeclaration($data);

        return response()->json($declaration, 201);
    }

    /**
     * Chantier 32.23: customsShow/customsCalculateDuties/customsSubmit/
     * customsClear all resolved a CustomsDeclaration by bare id with zero
     * tenant scoping — a real, confirmed cross-company IDOR distinct from
     * (and missed by) the Chantier 19 Lot 4 fix, which only scoped the
     * separate CustomsDeclarationController's own routes and explicitly
     * assumed this controller's `logistics/customs/{id}` path was
     * "unreachable from any UI page" — true for the frontend, but the real,
     * role-gated route is reachable by any direct API call regardless.
     * Confirmed empirically via a real cross-company HTTP request (company
     * B could read/submit/clear company A's declaration by id) before this
     * fix. Reuses the exact same real tenant_id column customsIndex()/
     * customsStore() already scope by.
     */
    private function findOwnedDeclaration(Request $request, int $id): CustomsDeclaration
    {
        $companyId = $request->user()->company_id ?? 0;

        return CustomsDeclaration::where('tenant_id', $companyId)->findOrFail($id);
    }

    /** GET /api/v1/logistics/customs/{id} */
    public function customsShow(Request $request, int $id): JsonResponse
    {
        $declaration = $this->findOwnedDeclaration($request, $id);

        $checklist = [];
        if ($declaration->incoterm && $declaration->country_of_destination) {
            $checklist = $this->customs->getDocumentChecklist(
                $declaration->incoterm,
                $declaration->country_of_destination
            );
        }

        return response()->json(['declaration' => $declaration, 'checklist' => $checklist]);
    }

    /** POST /api/v1/logistics/customs/{id}/calculate-duties */
    public function customsCalculateDuties(Request $request, int $id): JsonResponse
    {
        $this->findOwnedDeclaration($request, $id);

        return response()->json($this->customs->calculateDuties($id));
    }

    /** PUT /api/v1/logistics/customs/{id}/submit */
    public function customsSubmit(Request $request, int $id): JsonResponse
    {
        $this->findOwnedDeclaration($request, $id);

        return response()->json($this->customs->submit($id));
    }

    /** PUT /api/v1/logistics/customs/{id}/clear */
    public function customsClear(Request $request, int $id): JsonResponse
    {
        $this->findOwnedDeclaration($request, $id);

        $data = $request->validate([
            'duties_paid' => 'nullable|numeric|min:0',
            'vat_paid'    => 'nullable|numeric|min:0',
            'other_fees'  => 'nullable|numeric|min:0',
        ]);

        return response()->json($this->customs->clear($id, $data));
    }

    /** POST /api/v1/logistics/customs/hs-code-suggest */
    public function customsHsCodeSuggest(Request $request): JsonResponse
    {
        $data = $request->validate(['description' => 'required|string|max:500']);

        return response()->json($this->customs->getHsCode($data['description']));
    }

    /** GET /api/v1/logistics/customs/document-checklist */
    public function customsDocumentChecklist(Request $request): JsonResponse
    {
        $data = $request->validate([
            'incoterm'     => 'required|string|max:10',
            'dest_country' => 'required|string|size:2',
        ]);

        return response()->json($this->customs->getDocumentChecklist($data['incoterm'], $data['dest_country']));
    }

    // ── DELIVERY ROUTES ─────────────────────────────────────────────────────

    /** GET /api/v1/logistics/routes */
    public function routesIndex(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 0;

        $routes = DeliveryRoute::where('company_id', $companyId)
            ->with(['stops', 'vehicle'])
            ->when($request->input('status'),    fn($q, $v) => $q->where('status', $v))
            ->when($request->input('date'),      fn($q, $v) => $q->where('date', $v))
            ->when($request->input('driver_id'), fn($q, $v) => $q->where('driver_id', $v))
            ->orderByDesc('date')
            ->paginate(20);

        return response()->json($routes);
    }

    /** POST /api/v1/logistics/routes */
    public function routesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                         => 'required|string|max:200',
            'date'                         => 'required|date',
            'vehicle_id'                   => 'nullable|integer',
            'driver_id'                    => 'nullable|integer',
            'stops'                        => 'nullable|array',
            'stops.*.shipment_id'          => 'nullable|integer',
            'stops.*.type'                 => 'nullable|in:pickup,delivery,return',
            'stops.*.address'              => 'required_with:stops|string|max:500',
            'stops.*.lat'                  => 'nullable|numeric',
            'stops.*.lng'                  => 'nullable|numeric',
            'stops.*.planned_arrival'      => 'nullable|date_format:H:i',
            'stops.*.planned_duration_min' => 'nullable|integer|min:1',
            'stops.*.notes'                => 'nullable|string',
        ]);

        $data['company_id'] = $request->user()->company_id ?? 0;

        return response()->json($this->routeOpt->createRoute($data), 201);
    }

    /** PUT /api/v1/logistics/routes/{id}/optimize */
    public function routesOptimize(int $id): JsonResponse
    {
        return response()->json($this->routeOpt->optimizeRoute($id));
    }

    /** PUT /api/v1/logistics/routes/{id}/start */
    public function routesStart(int $id): JsonResponse
    {
        return response()->json($this->routeOpt->startRoute($id));
    }

    /** PUT /api/v1/logistics/routes/{routeId}/stops/{stopId}/complete */
    public function routeStopComplete(Request $request, int $routeId, int $stopId): JsonResponse
    {
        $data = $request->validate([
            'actual_arrival'    => 'nullable|date',
            'proof_of_delivery' => 'nullable|string',
            'signature_url'     => 'nullable|string|url',
            'notes'             => 'nullable|string',
        ]);

        return response()->json($this->routeOpt->completeStop($stopId, $data));
    }

    /** PUT /api/v1/logistics/routes/{id}/complete */
    public function routesComplete(int $id): JsonResponse
    {
        return response()->json($this->routeOpt->completeRoute($id));
    }

    /** GET /api/v1/logistics/routes/driver/{driverId} */
    public function routesDriverView(Request $request, int $driverId): JsonResponse
    {
        $date   = $request->input('date', now()->toDateString());
        $routes = $this->routeOpt->getDriverRoutes($driverId, $date);

        return response()->json(['date' => $date, 'routes' => $routes]);
    }

    // ── VEHICLES ────────────────────────────────────────────────────────────

    /** GET /api/v1/logistics/vehicles */
    public function vehiclesIndex(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 0;

        $vehicles = Vehicle::where('company_id', $companyId)
            ->when($request->input('status'), fn($q, $v) => $q->where('status', $v))
            ->when($request->input('type'),   fn($q, $v) => $q->where('type', $v))
            ->orderBy('name')
            ->paginate(20);

        return response()->json($vehicles);
    }

    // ── CARRIER INTEGRATIONS ─────────────────────────────────────────────────

    /** POST /api/v1/logistics/carriers/{id}/track */
    public function carrierTrack(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['tracking_number' => 'required|string|max:100']);

        return response()->json($this->carrierInt->trackShipment($id, $data['tracking_number']));
    }

    /** GET /api/v1/logistics/carriers/{id}/rate */
    public function carrierRate(Request $request, int $id): JsonResponse
    {
        $spec = $request->validate([
            'origin_country' => 'required|string|size:2',
            'dest_country'   => 'required|string|size:2',
            'weight_kg'      => 'required|numeric|min:0.001',
            'service_type'   => 'nullable|string|max:50',
        ]);

        $result = $this->carrierInt->getRate($id, $spec);

        if ($result === null) {
            return response()->json(['message' => 'Aucun tarif disponible pour cette route.'], 404);
        }

        return response()->json($result);
    }
}
