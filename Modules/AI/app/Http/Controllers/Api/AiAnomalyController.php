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

    private function resolveTenantId(Request $request): int
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable|null $user */
        $user = $request->user();

        if ($user && method_exists($user, 'tenant_id')) {
            return (int) $user->tenant_id;
        }

        if ($user && isset($user->tenant_id)) {
            return (int) $user->tenant_id;
        }

        return (int) $request->header('X-Tenant-Id', 1);
    }
}
