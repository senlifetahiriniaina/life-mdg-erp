<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Pipeline;

/**
 * @group CRM - Pipeline
 *
 * Configure CRM pipelines and stages.
 */
class PipelineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Pipeline::withCount('opportunities')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"));

        return response()->json($query->latest()->paginate(min((int) ($request->per_page ?? 25), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:crm_pipelines,name'],
            'is_default' => ['nullable', 'boolean'],
            'stages' => ['required', 'array', 'min:1'],
            'stages.*.name' => ['required', 'string', 'max:100'],
            'stages.*.order' => ['required', 'integer', 'min:0'],
            'stages.*.probability' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        if (! empty($validated['is_default'])) {
            Pipeline::where('is_default', true)->update(['is_default' => false]);
        }

        $pipeline = Pipeline::create($validated);

        return response()->json($pipeline, 201);
    }

    public function show(Pipeline $pipeline): JsonResponse
    {
        return response()->json($pipeline->load('opportunities'));
    }

    public function update(Request $request, Pipeline $pipeline): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:crm_pipelines,name,'.$pipeline->id],
            'is_default' => ['nullable', 'boolean'],
            'stages' => ['sometimes', 'array', 'min:1'],
            'stages.*.name' => ['required_with:stages', 'string', 'max:100'],
            'stages.*.order' => ['required_with:stages', 'integer', 'min:0'],
            'stages.*.probability' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        if (! empty($validated['is_default'])) {
            Pipeline::where('is_default', true)
                ->where('id', '!=', $pipeline->id)
                ->update(['is_default' => false]);
        }

        $pipeline->update($validated);

        return response()->json($pipeline->fresh());
    }

    public function destroy(Pipeline $pipeline): JsonResponse
    {
        $pipeline->delete();

        return response()->json(null, 204);
    }
}
