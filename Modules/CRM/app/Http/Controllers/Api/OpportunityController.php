<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\Pipeline;

/**
 * @group CRM - Opportunity
 *
 * Manage sales opportunities and deal stages.
 */
class OpportunityController extends Controller
{
    /**
     * List opportunities.
     *
     * Optimized with eager loading of all frequently-accessed relationships.
     *
     * @queryParam pipeline_id integer Filter by pipeline ID
     * @queryParam stage string Filter by stage name
     * @queryParam status string Filter by status (open, won, lost)
     * @queryParam owner_id integer Filter by owner user ID
     * @queryParam search string Search by opportunity name
     * @queryParam per_page integer Results per page (max 100)
     */
    public function index(Request $request): JsonResponse
    {
        $allowedSorts = ['name', 'amount', 'expected_close_date', 'created_at'];
        $sortParam = $request->sort ?? '-created_at';
        $sortDir = str_starts_with($sortParam, '-') ? 'desc' : 'asc';
        $sortCol = in_array(ltrim($sortParam, '-'), $allowedSorts) ? ltrim($sortParam, '-') : 'created_at';

        $query = Opportunity::with('account', 'contact', 'owner', 'pipeline', 'territory')
            ->when($request->pipeline_id, fn ($q, $v) => $q->where('pipeline_id', $v))
            ->when($request->stage, fn ($q, $v) => $q->where('stage', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->owner_id, fn ($q, $v) => $q->where('owner_id', $v))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->amount_min, fn ($q, $v) => $q->where('amount', '>=', $v))
            ->when($request->amount_max, fn ($q, $v) => $q->where('amount', '<=', $v))
            ->when($request->close_date_from, fn ($q, $v) => $q->whereDate('expected_close_date', '>=', $v))
            ->when($request->close_date_to ?? $request->close_date_before, fn ($q, $v) => $q->whereDate('expected_close_date', '<=', $v))
            ->orderBy($sortCol, $sortDir);

        return response()->json($query->paginate(min((int) ($request->per_page ?? 25), 100)));
    }

    /**
     * Get kanban board for a pipeline.
     *
     * Optimized query with eager loading and pagination (100 opportunities max).
     * Note: For large pipelines, results are limited to most recent 100 opportunities per stage.
     *
     * @queryParam pipeline_id integer Pipeline ID (defaults to default pipeline)
     * @queryParam limit integer Max opportunities to fetch (default 100, max 500)
     */
    public function kanban(Request $request): JsonResponse
    {
        $pipelineId = $request->pipeline_id
            ?? Pipeline::where('is_default', true)->value('id')
            ?? Pipeline::value('id');
        $limit = min((int) ($request->limit ?? 100), 500);

        $pipeline = $pipelineId ? Pipeline::find($pipelineId) : null;

        // No pipeline configured yet -> empty kanban board (graceful, not a 404).
        if (! $pipeline) {
            return response()->json([]);
        }

        // Eager load all relationships to avoid N+1
        $opportunities = Opportunity::with('account', 'contact', 'owner', 'territory')
            ->where('pipeline_id', $pipelineId)
            ->where('status', 'open')
            ->latest()
            ->limit($limit)
            ->get()
            ->groupBy('stage');

        // Stages may be stored as simple strings or as full dicts (name/order/probability).
        $board = collect($pipeline->stages)->map(function ($stage, $i) use ($opportunities) {
            $name = is_array($stage) ? ($stage['name'] ?? '') : $stage;
            $order = is_array($stage) ? ($stage['order'] ?? $i) : $i;
            $probability = is_array($stage) ? ($stage['probability'] ?? null) : null;

            return [
                'stage' => $name,
                'order' => $order,
                'probability' => $probability,
                'count' => $opportunities->get($name, collect())->count(),
                'opportunities' => $opportunities->get($name, collect())->take(50),
            ];
        });

        return response()->json($board->values());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pipeline_id' => ['nullable', 'exists:crm_pipelines,id'],
            'stage' => ['nullable', 'string'],
            'account_id' => ['nullable', 'exists:crm_accounts,id'],
            'contact_id' => ['nullable', 'exists:crm_contacts,id'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'expected_close_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:open,won,lost'],
            'description' => ['nullable', 'string'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        if (empty($validated['pipeline_id'])) {
            $validated['pipeline_id'] = \Modules\CRM\Models\Pipeline::where('is_default', true)->value('id')
                ?? \Modules\CRM\Models\Pipeline::create([
                    'name' => 'Default',
                    'is_default' => true,
                    'stages' => ['lead', 'qualified', 'proposal', 'negotiation', 'won', 'lost'],
                ])->id;
        }
        if (empty($validated['stage'])) {
            $validated['stage'] = 'lead';
        }

        $opportunity = Opportunity::create(array_merge($validated, [
            'owner_id' => $request->user()->id,
        ]));

        return response()->json($opportunity->load('account', 'contact', 'owner', 'pipeline'), 201);
    }

    public function show(Opportunity $opportunity): JsonResponse
    {
        $this->authorize('view', $opportunity);

        return response()->json($opportunity->load('account', 'contact', 'owner', 'pipeline'));
    }

    public function update(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorize('update', $opportunity);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'pipeline_id' => ['sometimes', 'exists:crm_pipelines,id'],
            'stage' => ['sometimes', 'string'],
            'account_id' => ['nullable', 'exists:crm_accounts,id'],
            'contact_id' => ['nullable', 'exists:crm_contacts,id'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'expected_close_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:open,won,lost'],
            'description' => ['nullable', 'string'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        if (isset($validated['status']) && in_array($validated['status'], ['won', 'lost'])) {
            $validated['closed_at'] = now();
        }

        $opportunity->update($validated);

        return response()->json($opportunity->fresh('account', 'contact', 'owner', 'pipeline'));
    }

    public function destroy(Opportunity $opportunity): JsonResponse
    {
        $this->authorize('delete', $opportunity);

        $opportunity->delete();

        return response()->json(null, 204);
    }

    public function pipeline(): JsonResponse
    {
        $summary = Opportunity::selectRaw('stage, COUNT(*) as count, SUM(amount) as total_amount')
            ->where('status', 'open')
            ->groupBy('stage')
            ->get()
            ->keyBy('stage')
            ->map(fn ($row) => ['count' => $row->count, 'total_amount' => $row->total_amount]);

        return response()->json($summary);
    }
}
