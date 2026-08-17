<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\ConsolidationHierarchy;

class ConsolidationHierarchyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConsolidationHierarchy::class);

        $hierarchies = ConsolidationHierarchy::with(['company', 'parentCompany', 'periods'])
            ->where('company_id', $request->user()->company_id)
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json(['data' => $hierarchies->items()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ConsolidationHierarchy::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:holding,subsidiary,branch,division',
            'parent_company_id' => 'nullable|exists:companies,id',
            // Not required from the client: always overwritten below with the
            // authenticated user's own company_id, so the client never needs
            // to know or send it.
            'company_id' => 'sometimes|exists:companies,id',
            'ownership_percentage' => 'required|numeric|between:0,100',
            'effective_date' => 'required|date',
            'end_date' => 'nullable|date|after:effective_date',
            'metadata' => 'nullable|json',
        ]);

        $validated['company_id'] = $request->user()->company_id;
        $hierarchy = ConsolidationHierarchy::create($validated);

        return response()->json(['data' => $hierarchy], 201);
    }

    public function show(ConsolidationHierarchy $hierarchy): JsonResponse
    {
        $this->authorize('view', $hierarchy);

        return response()->json(['data' => $hierarchy->load(['company', 'parentCompany', 'periods', 'eliminations'])]);
    }

    public function update(Request $request, ConsolidationHierarchy $hierarchy): JsonResponse
    {
        $this->authorize('update', $hierarchy);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'type' => 'in:holding,subsidiary,branch,division',
            'ownership_percentage' => 'numeric|between:0,100',
            'effective_date' => 'date',
            'end_date' => 'nullable|date|after:effective_date',
            'is_active' => 'boolean',
            'metadata' => 'nullable|json',
        ]);

        $hierarchy->update($validated);

        return response()->json(['data' => $hierarchy]);
    }

    public function destroy(ConsolidationHierarchy $hierarchy): JsonResponse
    {
        $this->authorize('delete', $hierarchy);

        $hierarchy->delete();

        return response()->json(null, 204);
    }
}
