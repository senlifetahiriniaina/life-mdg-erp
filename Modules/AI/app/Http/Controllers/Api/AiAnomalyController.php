<?php

declare(strict_types=1);

namespace Modules\AI\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\AI\Services\AiAnomalyDetectionService;

class AiAnomalyController extends Controller
{
    public function __construct(private readonly AiAnomalyDetectionService $service) {}

    /**
     * POST /api/v1/ai/anomalies/detect
     * Run anomaly detection for all modules for the current tenant.
     */
    public function detect(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        $anomalies = $this->service->getActiveAnomalies($tenantId);

        return response()->json([
            'data'  => $anomalies,
            'count' => count($anomalies),
        ]);
    }

    /**
     * GET /api/v1/ai/anomalies
     * List active anomalies for the current tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        $anomalies = $this->service->getActiveAnomalies($tenantId);

        return response()->json([
            'data'  => $anomalies,
            'count' => count($anomalies),
        ]);
    }

    /**
     * DELETE /api/v1/ai/anomalies/{id}
     * Dismiss an anomaly.
     */
    public function dismiss(Request $request, string $id): JsonResponse
    {
        $this->service->dismissAnomaly($id, $this->resolveTenantId($request));

        return response()->json(['message' => 'Anomaly dismissed.']);
    }

    /**
     * `users.tenant_id` is a real DB column but never in `App\Models\User::$fillable`
     * and never populated by the real registration flow, so `isset()`/`method_exists()`
     * on it was always false — every request fell through to the client-controlled
     * `X-Tenant-Id` header (default 1), letting any authenticated user read/dismiss
     * another tenant's anomalies by forging that header. `company_id` is this app's
     * real tenant boundary column (see `App\Http\Middleware\
     * InitializeTenancyFromAuthenticatedUser`'s docblock) — the header fallback is
     * dropped entirely rather than kept as a secondary path, since that fallback was
     * the actual vulnerability.
     */
    private function resolveTenantId(Request $request): int
    {
        return (int) ($request->user()?->company_id ?? 0);
    }
}
