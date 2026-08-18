<?php

namespace Modules\API\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function index(Request $request)
    {
        $query = DB::table('api_requests')->where('tenant_id', $this->tenantId($request));
        if ($request->api_key_id) {
            $query->where('api_key_id', $request->api_key_id);
        }
        if ($request->status_code) {
            $query->where('status_code', $request->status_code);
        }
        $logs = $query->orderByDesc('created_at')->paginate(50);
        return response()->json($logs);
    }

    public function stats(Request $request)
    {
        $stats = DB::table('api_requests')
            ->where('tenant_id', $this->tenantId($request))
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, AVG(duration_ms) as avg_ms, SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        return response()->json(['data' => $stats]);
    }
}
