<?php

namespace Modules\API\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\API\Models\ApiRequest;

class RequestLogController extends Controller
{
    /**
     * Chantier 10: same fix as ApiKeyController::tenantId() — every scoping
     * site here used the acting user's own id, not the real company_id
     * tenant boundary.
     */
    private function tenantId(Request $request): int
    {
        return (int) ($request->user()->company_id ?? 0);
    }

    /**
     * Chantier 32.5 (layer 14f — performance): already real pagination
     * (paginate(50), not ->get()) on a table this chantier's own
     * AuthenticateApiKey middleware can now genuinely grow large — verified
     * empirically against 5,000 seeded rows (see
     * Chantier32ApiDeepAuditTest.php) that this stays a single indexed
     * query with no N+1 (a raw query builder call, no relations to
     * eager-load).
     */
    public function index(Request $request)
    {
        $query = ApiRequest::query()->forTenant($this->tenantId($request));

        if ($request->filled('api_key_id')) {
            $query->where('api_key_id', $request->input('api_key_id'));
        }
        if ($request->filled('status_code')) {
            $query->where('status_code', $request->input('status_code'));
        }

        $logs = $query->orderByDesc('created_at')->paginate(50);

        return response()->json($logs);
    }

    public function stats(Request $request)
    {
        $stats = ApiRequest::query()
            ->forTenant($this->tenantId($request))
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, AVG(duration_ms) as avg_ms, SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $stats]);
    }
}
