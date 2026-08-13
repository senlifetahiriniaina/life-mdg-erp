<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Models\TenantExchange;
use Modules\Core\Services\TenantExchangeService;

/**
 * @group Core - Cross-Tenant Data Exchange
 *
 * Send, receive, and manage bilateral data-exchange requests between tenants.
 * The "current tenant" is derived from auth()->id() in single-tenant mode.
 */
class TenantExchangeController extends Controller
{
    public function __construct(private readonly TenantExchangeService $service)
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Send a new exchange request.
     *
     * POST /api/v1/core/exchanges
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_tenant' => ['required', 'string'],
            'exchange_type' => ['required', 'string', 'in:catalog_share,contact_share,order_reference,quote_share,product_share'],
            'payload' => ['required', 'array'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $sourceTenantId = (string) $request->user()->id;

        try {
            $exchange = $this->service->sendExchange(
                $sourceTenantId,
                $data['target_tenant'],
                $data['exchange_type'],
                $data['payload'],
                $request->user()->id,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (! empty($data['message'])) {
            $exchange->update(['message' => $data['message']]);
        }

        return response()->json($exchange->load('history'), 201);
    }

    /**
     * List exchanges received by the current tenant (incoming).
     *
     * GET /api/v1/core/exchanges/incoming
     */
    public function incoming(Request $request): JsonResponse
    {
        $tenantId = (string) $request->user()->id;

        $exchanges = TenantExchange::query()
            ->where('target_tenant_id', $tenantId)
            ->with('history')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $exchanges]);
    }

    /**
     * List exchanges sent by the current tenant (outgoing).
     *
     * GET /api/v1/core/exchanges/outgoing
     */
    public function outgoing(Request $request): JsonResponse
    {
        $tenantId = (string) $request->user()->id;

        $exchanges = TenantExchange::query()
            ->where('source_tenant_id', $tenantId)
            ->with('history')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $exchanges]);
    }

    /**
     * Show a single exchange.
     *
     * GET /api/v1/core/exchanges/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = (string) $request->user()->id;

        $exchange = TenantExchange::with('history')->findOrFail($id);

        // Only source or target tenant may view the exchange.
        if ($exchange->source_tenant_id !== $tenantId && $exchange->target_tenant_id !== $tenantId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($exchange);
    }

    /**
     * Accept an incoming exchange.
     *
     * POST /api/v1/core/exchanges/{id}/accept
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        $tenantId = (string) $request->user()->id;

        $exchange = TenantExchange::findOrFail($id);

        if ($exchange->target_tenant_id !== $tenantId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        try {
            $this->service->accept($exchange, $request->user()->id);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($exchange->fresh()->load('history'));
    }

    /**
     * Reject an incoming exchange.
     *
     * POST /api/v1/core/exchanges/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $tenantId = (string) $request->user()->id;

        $exchange = TenantExchange::findOrFail($id);

        if ($exchange->target_tenant_id !== $tenantId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        try {
            $this->service->reject($exchange, $request->user()->id, $data['reason'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($exchange->fresh()->load('history'));
    }

    /**
     * Cancel an outgoing exchange (sender only).
     *
     * DELETE /api/v1/core/exchanges/{id}
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $tenantId = (string) $request->user()->id;

        $exchange = TenantExchange::findOrFail($id);

        if ($exchange->source_tenant_id !== $tenantId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        try {
            $this->service->cancel($exchange, $request->user()->id);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Exchange cancelled.']);
    }
}
