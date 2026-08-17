<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\DepreciationPolicy;

class DepreciationPolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DepreciationPolicy::class);

        $policies = DepreciationPolicy::with('company')
            ->where('company_id', $request->user()->company_id)
            ->when($request->filled('asset_category'), fn($q) => $q->where('asset_category', $request->asset_category))
            ->when($request->filled('depreciation_method'), fn($q) => $q->where('depreciation_method', $request->depreciation_method))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($policies);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', DepreciationPolicy::class);

        $validated = $request->validate([
            'policy_name' => 'required|string|max:255',
            'asset_category' => 'required|string|max:255',
            'depreciation_method' => 'required|in:straight_line,declining_balance,units_of_production,sum_of_years,macrs',
            'default_useful_life_years' => 'required|integer|min:1',
            'default_residual_percentage' => 'required|numeric|between:0,100',
            'tax_depreciation_method' => 'nullable|string',
            'tax_useful_life_years' => 'nullable|integer|min:1',
            'policy_description' => 'nullable|string',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        $validated['company_id'] = $request->user()->company_id;
        $validated['is_active'] = true;

        $policy = DepreciationPolicy::create($validated);

        return response()->json($policy, 201);
    }

    public function show(DepreciationPolicy $policy): JsonResponse
    {
        $this->authorize('view', $policy);

        return response()->json($policy->load('company'));
    }

    public function update(Request $request, DepreciationPolicy $policy): JsonResponse
    {
        $this->authorize('update', $policy);

        $validated = $request->validate([
            'policy_name' => 'string|max:255',
            'default_useful_life_years' => 'integer|min:1',
            'default_residual_percentage' => 'numeric|between:0,100',
            'tax_depreciation_method' => 'nullable|string',
            'tax_useful_life_years' => 'nullable|integer|min:1',
            'policy_description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $policy->update($validated);

        return response()->json($policy);
    }

    public function destroy(DepreciationPolicy $policy): JsonResponse
    {
        $this->authorize('delete', $policy);

        $policy->delete();

        return response()->json(null, 204);
    }
}
