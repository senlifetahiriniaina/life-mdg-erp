<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Http\Requests\StoreRecurringOrderTemplateRequest;
use Modules\Sales\Http\Requests\UpdateRecurringOrderTemplateRequest;
use Modules\Sales\Models\RecurringOrderTemplate;
use Modules\Sales\Services\RecurringOrderService;

/**
 * Chantier 25 (volet E de la feuille de route Chantier 21) — commandes
 * récurrentes. Gate sur les mêmes permissions plates sales.{read,create,
 * update} que SalesController (pas de classe Policy dans ce module — même
 * convention établie).
 */
class RecurringOrderTemplateController extends Controller
{
    public function __construct(private readonly RecurringOrderService $service)
    {
    }

    private function tenantId(Request $request): int
    {
        return (int) ($request->user()?->company_id ?? 0);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sales.read'), 403);

        $templates = RecurringOrderTemplate::forTenant($this->tenantId($request))
            ->with('lines')
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($templates);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.read'), 403);

        $template = RecurringOrderTemplate::forTenant($this->tenantId($request))->with('lines')->findOrFail($id);

        return response()->json(['data' => $template]);
    }

    public function store(StoreRecurringOrderTemplateRequest $request): JsonResponse
    {
        abort_unless($request->user()->can('sales.create'), 403);

        $template = $this->service->create($request->validated(), $this->tenantId($request), $request->user()->id);

        return response()->json(['data' => $template], 201);
    }

    public function update(UpdateRecurringOrderTemplateRequest $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $template = RecurringOrderTemplate::forTenant($this->tenantId($request))->findOrFail($id);
        $template = $this->service->update($template, $request->validated());

        return response()->json(['data' => $template]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.update'), 403);

        $template = RecurringOrderTemplate::forTenant($this->tenantId($request))->findOrFail($id);
        $template->delete();

        return response()->json(null, 204);
    }

    public function runNow(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('sales.create'), 403);

        $template = RecurringOrderTemplate::forTenant($this->tenantId($request))->findOrFail($id);
        $order = $this->service->runNow($template);

        return response()->json(['data' => $order->load('lines'), 'template' => $template->fresh()], 201);
    }
}
