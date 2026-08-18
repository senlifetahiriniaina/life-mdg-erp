<?php

declare(strict_types=1);

namespace Modules\AI\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\AI\Services\AiNaturalLanguageSearchService;

class AiSearchController extends Controller
{
    public function __construct(private readonly AiNaturalLanguageSearchService $service) {}

    /**
     * POST /api/v1/ai/search
     * Perform a natural language search.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query'  => 'required|string|min:2|max:500',
            'locale' => 'sometimes|string|in:fr,en,ar,sw,mg,ha,zh,hi,es,pt',
        ]);

        $tenantId = $this->resolveTenantId($request);
        $query    = (string) $request->input('query');
        $locale   = (string) $request->input('locale', 'fr');

        $result = $this->service->search($query, $tenantId, $locale);

        return response()->json($result);
    }

    /**
     * See `AiAnomalyController::resolveTenantId()` — same fix. `users.tenant_id` is
     * never populated by the real registration flow, so this always fell through to
     * the client-controlled `X-Tenant-Id` header (default 1), letting any
     * authenticated user run NL search scoped to another tenant by forging that
     * header. `company_id` is this app's real tenant boundary column; the header
     * fallback is dropped entirely, not kept as a secondary path.
     */
    private function resolveTenantId(Request $request): int
    {
        return (int) ($request->user()?->company_id ?? 0);
    }
}
