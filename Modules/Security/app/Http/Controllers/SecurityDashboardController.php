<?php

declare(strict_types=1);

namespace Modules\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Modules\Security\Services\SecurityAuditService;

/**
 * Chantier 32.3 (14-layer deep audit): SecurityAuditService::getSecuritySummary()
 * was a real, correct method with zero real callers anywhere in the app —
 * Index.vue instead made 4 separate axios calls and recomputed the same
 * numbers client-side, and its 'critical_threats' figure was never even
 * attempted (the service itself hardcoded it to 0). This controller gives
 * the service a real caller and Index.vue one authoritative, cached (5 min,
 * see the service) endpoint instead of duplicating the aggregation logic.
 */
class SecurityDashboardController extends Controller
{
    public function __construct(private readonly SecurityAuditService $auditService) {}

    public function summary(): JsonResponse
    {
        $companyId = (string) (auth()->user()->company_id ?? 0);

        return response()->json(['data' => $this->auditService->getSecuritySummary($companyId)]);
    }
}
