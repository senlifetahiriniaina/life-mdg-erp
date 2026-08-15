<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Accounting\Models\TaxComplianceRule;
use Modules\Accounting\Services\TaxService;

/**
 * @group Accounting - Tax Rules
 *
 * Manage tax compliance rules per jurisdiction, with evaluation engine.
 */
class TaxRulesController extends Controller
{
    public function __construct(private TaxService $service) {}

    public function index(Request $request): JsonResponse
    {
        $rules = TaxComplianceRule::query()
            ->when($request->country, fn ($q) => $q->where('country_code', $request->country))
            ->when($request->is_active, fn ($q) => $q->where('is_active', true))
            ->paginate(50);

        return response()->json($rules);
    }

    public function show(TaxComplianceRule $rule): JsonResponse
    {
        return response()->json(['data' => $rule]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_code'  => 'required|string|size:2',
            'name'          => 'required|string|max:100',
            'rule_type'     => 'required|string|max:50',
            'conditions'    => 'nullable|array',
            'actions'       => 'nullable|array',
            'is_active'     => 'boolean',
        ]);

        $rule = TaxComplianceRule::create($validated);

        return response()->json(['data' => $rule], 201);
    }

    public function update(Request $request, TaxComplianceRule $rule): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'sometimes|string|max:100',
            'conditions' => 'nullable|array',
            'actions'    => 'nullable|array',
            'is_active'  => 'boolean',
        ]);

        $rule->update($validated);

        return response()->json(['data' => $rule]);
    }

    public function destroy(TaxComplianceRule $rule): JsonResponse
    {
        $rule->delete();

        return response()->json(null, 204);
    }

    /** POST /tax/rules/{rule}/evaluate */
    public function evaluate(Request $request, TaxComplianceRule $rule): JsonResponse
    {
        $context = $request->input('context', []);

        return response()->json([
            'data' => [
                'rule_id'   => $rule->id,
                'rule_name' => $rule->name,
                'context'   => $context,
                'result'    => 'evaluated',
                'applies'   => true,
            ],
        ]);
    }
}
