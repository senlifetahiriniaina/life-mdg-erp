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
/**
 * Chantier "CRM tenant-isolation follow-up": crm_pipelines had no company/tenant column at all
 * and this controller had zero `authorize()` calls anywhere — any authenticated CRM-module user
 * could list/view/edit/delete any other company's pipeline stage configuration, confirmed via
 * read before this fix. Rewired onto a new, additive `company_id` column plus a new
 * PipelinePolicy, matching the ContactController/ContactPolicy pattern. `is_default` is kept a
 * genuinely global toggle (Pipeline::where('is_default', true)->update(...) is intentionally
 * unscoped) only in the sense that it's now scoped by the same company_id filter added to the
 * surrounding query below — one company's "make this the default" no longer touches another's.
 */
class PipelineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Pipeline::class);

        $query = Pipeline::withCount('opportunities')
            ->where('company_id', $request->user()->company_id)
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"));

        return response()->json($query->latest()->paginate(min((int) ($request->per_page ?? 25), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Pipeline::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:crm_pipelines,name'],
            'is_default' => ['nullable', 'boolean'],
            'stages' => ['required', 'array', 'min:1'],
            'stages.*.name' => ['required', 'string', 'max:100'],
            'stages.*.order' => ['required', 'integer', 'min:0'],
            'stages.*.probability' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        if (! empty($validated['is_default'])) {
            Pipeline::where('is_default', true)
                ->where('company_id', $request->user()->company_id)
                ->update(['is_default' => false]);
        }

        $pipeline = Pipeline::create(array_merge($validated, [
            'company_id' => $request->user()->company_id,
        ]));

        return response()->json($pipeline, 201);
    }

    public function show(Pipeline $pipeline): JsonResponse
    {
        $this->authorize('view', $pipeline);

        return response()->json($pipeline->load('opportunities'));
    }

    public function update(Request $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorize('update', $pipeline);

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
                ->where('company_id', $request->user()->company_id)
                ->where('id', '!=', $pipeline->id)
                ->update(['is_default' => false]);
        }

        $pipeline->update($validated);

        return response()->json($pipeline->fresh());
    }

    public function destroy(Pipeline $pipeline): JsonResponse
    {
        $this->authorize('delete', $pipeline);

        $pipeline->delete();

        return response()->json(null, 204);
    }
}
