<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Models\SalesObjective;
use Modules\Sales\Services\SalesObjectiveService;

/**
 * Chantier 26 (volet B) — objectifs commerciaux assistés par IA. Propose
 * 2-3 cibles de chiffre d'affaires (jamais inventées — dérivées de
 * l'historique réel), modifiables puis validables sur l'interface.
 */
class SalesObjectiveController extends Controller
{
    public function __construct(private readonly SalesObjectiveService $service) {}

    private function tenantId(Request $request): int
    {
        return (int) ($request->user()?->company_id ?? 0);
    }

    /**
     * GET /api/v1/sales/objectives?scope=&scope_ref_id=&status=&period_start=&period_end=
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sales.read'), 403);

        $query = SalesObjective::query()->forTenant($this->tenantId($request));

        if ($request->filled('scope')) {
            $query->where('scope', $request->string('scope'));
        }
        if ($request->has('scope_ref_id')) {
            $query->where('scope_ref_id', $request->integer('scope_ref_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('period_start')) {
            $query->whereDate('period_start', $request->date('period_start'));
        }

        $objectives = $query->orderByDesc('created_at')->get();

        return response()->json(['data' => $objectives]);
    }

    /**
     * POST /api/v1/sales/objectives/propose
     * body: scope, scope_ref_id (requis sauf scope=global), period_start, period_end
     */
    public function propose(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sales.create'), 403);

        $validated = $request->validate([
            'scope'        => 'required|string|in:'.implode(',', SalesObjective::SCOPES),
            'scope_ref_id' => 'required_unless:scope,global|nullable|integer',
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
        ]);

        $tenantId = $this->tenantId($request);

        $objectives = $this->service->proposeObjectives(
            scope: $validated['scope'],
            scopeRefId: $validated['scope'] === 'global' ? null : (int) $validated['scope_ref_id'],
            tenantId: $tenantId,
            periodStart: Carbon::parse($validated['period_start']),
            periodEnd: Carbon::parse($validated['period_end']),
            createdBy: $request->user()->id,
        );

        return response()->json(['data' => $objectives], 201);
    }

    /**
     * PUT /api/v1/sales/objectives/{objective}
     * Seule une proposition encore 'proposed' peut être modifiée.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $objective = SalesObjective::forTenant($this->tenantId($request))->findOrFail($id);

        if ($objective->status !== 'proposed') {
            return response()->json(['message' => 'Seule une proposition non validée peut être modifiée.'], 422);
        }

        $validated = $request->validate([
            'target_amount' => 'sometimes|numeric|min:0',
            'period_start'  => 'sometimes|date',
            'period_end'    => 'sometimes|date|after_or_equal:period_start',
        ]);

        $objective->fill($validated);
        $objective->save();

        return response()->json($objective);
    }

    /**
     * POST /api/v1/sales/objectives/{objective}/validate
     * Valide cette proposition et rejette automatiquement les autres du même
     * (scope, scope_ref_id, période).
     */
    public function validateObjective(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $objective = SalesObjective::forTenant($this->tenantId($request))->findOrFail($id);

        if ($objective->status === 'validated') {
            return response()->json(['message' => 'Cet objectif est déjà validé.'], 422);
        }

        $validated = $request->validate([
            'target_amount' => 'sometimes|numeric|min:0',
        ]);

        $objective = $this->service->validate($objective, $request->user()->id, $validated);

        return response()->json($objective);
    }

    /**
     * DELETE /api/v1/sales/objectives/{objective}
     * Seule une proposition encore 'proposed' peut être supprimée.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $objective = SalesObjective::forTenant($this->tenantId($request))->findOrFail($id);

        if ($objective->status !== 'proposed') {
            return response()->json(['message' => 'Seule une proposition non validée peut être supprimée.'], 422);
        }

        $objective->delete();

        return response()->json(null, 204);
    }
}
