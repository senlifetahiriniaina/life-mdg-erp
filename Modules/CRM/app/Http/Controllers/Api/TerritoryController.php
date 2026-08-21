<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\Territory;
use Modules\CRM\Services\TerritoryForecastService;
use Modules\CRM\Services\TerritoryService;

/**
 * @group CRM - Territory Management
 *
 * Manage CRM territories and Einstein-style pipeline forecasting.
 */
class TerritoryController extends Controller
{
    public function __construct(
        private TerritoryForecastService $forecastService,
        private TerritoryService $territoryService,
    ) {}

    /**
     * List territories.
     *
     * Chantier 32.15: had zero tenant scoping — any authenticated CRM-module user of any
     * company could list/read/create/update/delete/forecast every other company's sales
     * territories (region, quota, assigned rep), confirmed empirically before this fix.
     * crm_territories never had a tenant/company column of any kind — a new, additive
     * company_id column was added alongside this fix.
     *
     * @queryParam search string Filter by name or code. Example: North
     * @queryParam is_active boolean Filter by active status. Example: true
     * @queryParam per_page integer Results per page (max 100). Example: 25
     *
     * @response 200 scenario="Success" {"data": [{"id": 1, "name": "North Territory", "code": "NT", "sales_target": 500000, "ytd_revenue": 125000, "quota_attainment": 25}], "meta": {"current_page": 1, "total": 10}}
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Territory::class);

        $perPage = min((int) ($request->per_page ?? 25), 100);

        $query = Territory::query()
            ->with(['assignedTo', 'opportunities'])
            ->where('company_id', $request->user()->company_id);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%");
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $territories = $query->paginate($perPage);

        // Enrich with metrics
        $territories->getCollection()->transform(function (Territory $territory): array {
            return [
                'id' => $territory->id,
                'name' => $territory->name,
                'code' => $territory->code,
                'assigned_to_name' => $territory->assignedTo->name ?? 'Unassigned',
                'region' => $territory->region,
                'sales_target' => (float) $territory->sales_target,
                'currency' => $territory->currency,
                'is_active' => $territory->is_active,
                'open_opportunities' => $territory->opportunities()
                    ->where('status', '!=', 'closed_lost')
                    ->count(),
                'ytd_revenue' => $territory->ytdRevenue(),
                'quota_attainment' => $territory->quotaAttainment(),
                'forecast_revenue' => $territory->forecastedRevenue(),
            ];
        });

        return response()->json($territories);
    }

    /**
     * Create territory.
     *
     * @bodyParam name string required Territory name. Example: "West Territory"
     * @bodyParam code string required Unique territory code. Example: "WT"
     * @bodyParam assigned_to integer required User ID of assigned sales manager. Example: 1
     * @bodyParam sales_target number required Annual sales target. Example: 500000
     * @bodyParam currency string Territory currency (default: USD). Example: "USD"
     * @bodyParam description string Optional description. Example: "Western region territory"
     * @bodyParam region string Optional region. Example: "West Coast"
     * @bodyParam is_active boolean Territory is active (default: true). Example: true
     * @bodyParam parent_territory_id integer Optional parent territory ID. Example: 1
     *
     * @response 201 scenario="Success" {"id": 1, "name": "West Territory", "code": "WT", "sales_target": 500000}
     * @response 422 scenario="Validation error" {"message": "The given data was invalid.", "errors": {"code": ["The code has already been taken."]}}
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Territory::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:crm_territories,code',
            'assigned_to' => 'required|exists:users,id',
            'sales_target' => 'required|numeric|min:0',
            'currency' => 'string|max:3',
            'description' => 'nullable|string',
            'region' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'parent_territory_id' => 'nullable|exists:crm_territories,id',
            'year_start_date' => 'nullable|date',
        ]);

        // Apply defaults
        $validated['currency'] = $validated['currency'] ?? 'USD';
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['company_id'] = $request->user()->company_id;

        $territory = Territory::create($validated);

        return response()->json($territory, 201);
    }

    /**
     * Show territory with metrics.
     *
     * @response 200 scenario="Success" {"id": 1, "name": "West Territory", "code": "WT", "ytd_revenue": 125000, "quota_attainment": 25, "forecast_revenue": 350000, "quota_forecast": 70}
     * @response 404 scenario="Not found" {}
     */
    public function show(Territory $territory): JsonResponse
    {
        $this->authorize('view', $territory);

        $territory->load(['assignedTo', 'opportunities.score']);

        return response()->json([
            'id' => $territory->id,
            'name' => $territory->name,
            'code' => $territory->code,
            'description' => $territory->description,
            'region' => $territory->region,
            'assigned_to_id' => $territory->assigned_to,
            'assigned_to_name' => $territory->assignedTo->name ?? 'Unassigned',
            'sales_target' => (float) $territory->sales_target,
            'currency' => $territory->currency,
            'is_active' => $territory->is_active,
            'year_start_date' => $territory->year_start_date?->format('Y-m-d'),
            'ytd_revenue' => $territory->ytdRevenue(),
            'quota_attainment' => $territory->quotaAttainment(),
            'forecast_revenue' => $territory->forecastedRevenue(),
            'quota_forecast' => $territory->quotaForecast(),
            'open_opportunities' => $territory->opportunities()
                ->where('status', '!=', 'closed_lost')
                ->count(),
        ]);
    }

    /**
     * Update territory.
     *
     * @bodyParam name string Territory name. Example: "West Territory"
     * @bodyParam code string Unique territory code. Example: "WT"
     * @bodyParam assigned_to integer User ID of assigned sales manager. Example: 1
     * @bodyParam sales_target number Annual sales target. Example: 500000
     *
     * @response 200 scenario="Success" {"id": 1, "name": "West Territory", "code": "WT"}
     * @response 404 scenario="Not found" {}
     */
    public function update(Request $request, Territory $territory): JsonResponse
    {
        $this->authorize('update', $territory);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'code' => 'string|max:50|unique:crm_territories,code,'.$territory->id,
            'assigned_to' => 'exists:users,id',
            'sales_target' => 'numeric|min:0',
            'currency' => 'string|max:3',
            'description' => 'nullable|string',
            'region' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'parent_territory_id' => 'nullable|exists:crm_territories,id',
            'year_start_date' => 'nullable|date',
        ]);

        $territory->update($validated);

        return response()->json($territory);
    }

    /**
     * Delete territory.
     *
     * @response 200 scenario="Success" {}
     * @response 404 scenario="Not found" {}
     * @response 409 scenario="Has child territories or opportunities" {"message": "Cannot delete territory with child territories or active opportunities."}
     */
    public function destroy(Territory $territory): JsonResponse
    {
        $this->authorize('delete', $territory);

        // Check for child territories or associated opportunities
        if ($territory->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete territory with child territories.',
            ], 409);
        }

        if ($territory->opportunities()->exists()) {
            return response()->json([
                'message' => 'Cannot delete territory with associated opportunities.',
            ], 409);
        }

        $territory->delete();

        return response()->json(status: 200);
    }

    /**
     * Get detailed forecast for a territory.
     *
     * @response 200 scenario="Success" {"territory": {...}, "stages": {...}, "timeline": [...], "at_risk_count": 2}
     * @response 404 scenario="Not found" {}
     */
    public function forecast(Territory $territory): JsonResponse
    {
        $this->authorize('view', $territory);

        $forecast = $this->forecastService->territoryDetail($territory);

        return response()->json($forecast);
    }

    /**
     * Get at-risk opportunities for a territory.
     *
     * @queryParam per_page integer Results per page (max 100). Example: 25
     *
     * @response 200 scenario="Success" {"data": [...], "meta": {"total": 3}}
     * @response 404 scenario="Not found" {}
     */
    public function atRisk(Request $request, Territory $territory): JsonResponse
    {
        $this->authorize('view', $territory);

        $perPage = min((int) ($request->per_page ?? 25), 100);
        $page = (int) ($request->page ?? 1);

        $atRiskOpps = $this->forecastService->atRiskOpportunities($territory);

        // Manually paginate the collection
        $items = $atRiskOpps->forPage($page, $perPage);
        $paginated = new Paginator(
            $items,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json($paginated);
    }

    /**
     * Assign an opportunity to a territory.
     *
     * @bodyParam opportunity_id integer required ID of opportunity to assign. Example: 5
     *
     * @response 200 scenario="Success" {"id": 5, "name": "Deal ABC", "territory_id": 1}
     * @response 404 scenario="Not found" {}
     * @response 422 scenario="Validation error" {"message": "The given data was invalid.", "errors": {"opportunity_id": ["The opportunity_id field is required."]}}
     */
    public function assignOpportunity(Request $request, Territory $territory): JsonResponse
    {
        $this->authorize('update', $territory);

        $validated = $request->validate([
            'opportunity_id' => 'required|exists:crm_opportunities,id',
        ]);

        $opportunity = Opportunity::findOrFail($validated['opportunity_id']);
        // Chantier 32.15: the opportunity itself was never authorized — a caller could assign
        // another company's opportunity into their own territory (or vice versa) purely by
        // guessing an opportunity_id, even though the territory-side check above is correct.
        $this->authorize('update', $opportunity);
        $updated = $this->forecastService->assignOpportunity($opportunity, $territory);

        return response()->json([
            'id' => $updated->id,
            'name' => $updated->name,
            'territory_id' => $updated->territory_id,
            'amount' => (float) $updated->amount,
            'stage' => $updated->stage,
            'status' => $updated->status,
        ]);
    }

    /**
     * Auto-assign a contact to a territory based on rules JSON.
     *
     * @bodyParam contact_id integer required ID of the contact to auto-assign. Example: 1
     */
    public function autoAssign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:crm_contacts,id',
        ]);

        $contact = Contact::findOrFail($validated['contact_id']);
        // Chantier 32.15: was missing entirely — any authenticated CRM-module user could
        // auto-assign any other company's contact into a territory purely by guessing an id.
        $this->authorize('view', $contact);
        $assignment = $this->territoryService->autoAssign($contact, $request->user()->company_id);

        if ($assignment === null) {
            return response()->json(['message' => 'No matching territory found for this contact.'], 422);
        }

        return response()->json($assignment);
    }

    /**
     * Get team quota attainment for all territories.
     *
     * @response 200 scenario="Success" [{"territory_id": 1, "territory_name": "West", "quota": 500000, "ytd_revenue": 125000, "attainment_pct": 25}]
     */
    public function teamQuotas(Request $request): JsonResponse
    {
        return response()->json($this->territoryService->getTeamQuotas($request->user()->company_id));
    }

    /**
     * Coverage summary: how many active territories have at least one
     * assigned account/contact, and which don't (gaps).
     *
     * @response 200 scenario="Success" {"total": 10, "assigned": 8, "unassigned": 2, "percentage": 80, "gaps": [...]}
     */
    public function coverage(Request $request): JsonResponse
    {
        return response()->json($this->territoryService->coverage($request->user()->company_id));
    }

    /**
     * Rebalance territory assignments evenly across active territories.
     *
     * @response 200 scenario="Success" {"rebalanced": 12, "territories": [...]}
     */
    public function rebalance(Request $request): JsonResponse
    {
        return response()->json($this->territoryService->rebalance($request->user()->company_id));
    }

    /**
     * Get Einstein-style forecast by all territories.
     *
     * @response 200 scenario="Success" {"territories": {...}, "summary": {"total_forecast": 2500000, "total_target": 5000000, "aggregate_quota_forecast": 50}}
     */
    public function territoryForecast(Request $request): JsonResponse
    {
        $forecast = $this->forecastService->territoryForecast($request->user()->company_id);

        return response()->json($forecast);
    }

    /**
     * Get forecast vs target comparison for a territory.
     *
     * @queryParam territory_id integer required ID of territory. Example: 1
     *
     * @response 200 scenario="Success" {"territory_id": 1, "territory_name": "West Territory", "ytd_revenue": 125000, "sales_target": 500000, "forecast_revenue": 350000, "variance": -150000, "variance_percent": -30, "status": "at_risk"}
     * @response 422 scenario="Validation error" {"message": "The given data was invalid.", "errors": {"territory_id": ["The territory_id field is required."]}}
     */
    public function forecastComparison(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'territory_id' => 'required|exists:crm_territories,id',
        ]);

        $territory = Territory::findOrFail($validated['territory_id']);
        // Chantier 32.15: was missing entirely — territory_id comes from the request body,
        // not route-model-binding, so no policy check ever ran against it.
        $this->authorize('view', $territory);
        $comparison = $this->forecastService->forecastVsTarget($territory);

        return response()->json($comparison);
    }
}
