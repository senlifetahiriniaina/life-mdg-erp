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

    private function resolveTenantId(Request $request): int
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable|null $user */
        $user = $request->user();

        if ($user && isset($user->tenant_id)) {
            return (int) $user->tenant_id;
        }

        return (int) $request->header('X-Tenant-Id', 1);
    }
}
