<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\BI\Models\Dashboard;
use Modules\BI\Services\EmbedTokenService;

/**
 * @group BI - Embed
 *
 * Signed embed tokens for white-label / external dashboard access.
 *
 * Workflow:
 *   1. Authenticated admin POSTs to `/bi/embed/tokens` → receives a signed JWT.
 *   2. Consumer embeds `/bi/embed/dashboard/{id}?embed_token=<jwt>` in an iframe.
 *   3. GET `/bi/embed/validate?token=<jwt>` can be called client-side to confirm validity.
 */
class EmbedController extends Controller
{
    public function __construct(
        private readonly EmbedTokenService $embedService,
    ) {}

    // -------------------------------------------------------------------------
    // POST /bi/embed/tokens  (auth required)
    // -------------------------------------------------------------------------

    /**
     * Create an embed token for a dashboard.
     *
     * @bodyParam dashboard_id int required Dashboard to embed. Example: 7
     * @bodyParam allowed_domains string[] required Allowed iframe origins. Example: ["example.com"]
     * @bodyParam expires_in int Seconds until expiry (60–604800). Default: 3600. Example: 86400
     */
    public function createToken(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->can('create', Dashboard::class)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'dashboard_id'    => ['required', 'integer', 'exists:bi_dashboards,id'],
            'allowed_domains' => ['required', 'array', 'min:1'],
            'allowed_domains.*' => ['string', 'max:253'],
            'expires_in'      => ['sometimes', 'integer', 'min:60', 'max:604800'],
        ]);

        // Ensure the dashboard belongs to the same tenant
        $dashboard = Dashboard::findOrFail($data['dashboard_id']);
        // tenant_id guard — if tenant_id exists on dashboard, verify match
        if (
            isset($dashboard->tenant_id)
            && $dashboard->tenant_id !== ($user->tenant_id ?? $dashboard->tenant_id)
        ) {
            return response()->json(['message' => 'Dashboard not found.'], 404);
        }

        $tenantId = $user->tenant_id ?? 0;
        $result   = $this->embedService->generateEmbedToken(
            dashboardId:    $data['dashboard_id'],
            tenantId:       $tenantId,
            allowedDomains: $data['allowed_domains'],
            expiresIn:      $data['expires_in'] ?? 3600,
            createdBy:      $user->id,
        );

        return response()->json([
            'token'          => $result['token'],
            'expires_at'     => $result['expires_at'],
            'embed_token_id' => $result['embed_token_id'],
            'embed_url'      => url("/api/v1/bi/embed/dashboard/{$data['dashboard_id']}"),
        ], 201);
    }

    // -------------------------------------------------------------------------
    // GET /bi/embed/validate  (public — no auth)
    // -------------------------------------------------------------------------

    /**
     * Validate an embed token without auth.
     *
     * @queryParam token string required The JWT embed token. Example: eyJ...
     */
    public function validateToken(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        try {
            $payload = $this->embedService->validateEmbedToken($request->query('token'));

            return response()->json([
                'valid'        => true,
                'dashboard_id' => $payload['dashboard_id'],
                'expires_at'   => date('c', $payload['exp']),
                'domains'      => $payload['domains'],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['valid' => false, 'reason' => $e->getMessage()], 401);
        }
    }

    // -------------------------------------------------------------------------
    // GET /bi/embed/dashboard/{id}  (public with embed token)
    // -------------------------------------------------------------------------

    /**
     * Fetch dashboard widget data via embed token.
     *
     * The `embed_token` query parameter replaces normal auth.
     * Returns read-only widget data scoped to the token's dashboard_id.
     *
     * @queryParam embed_token string required Signed JWT embed token. Example: eyJ...
     */
    public function getDashboard(Request $request, int $id): JsonResponse
    {
        $token = $request->query('embed_token');
        if (! $token) {
            return response()->json(['message' => 'embed_token query parameter required.'], 401);
        }

        try {
            $payload = $this->embedService->validateEmbedToken($token);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        // Scoped access — the token must be for this specific dashboard
        if ((int) $payload['dashboard_id'] !== $id) {
            return response()->json(['message' => 'Token not valid for this dashboard.'], 403);
        }

        // CORS: add allowed-origin header if request has Origin header
        $origin = $request->header('Origin', '');
        $corsOk = $origin === '' || $this->embedService->isAllowedOrigin($origin, $payload['domains']);
        if ($origin !== '' && ! $corsOk) {
            return response()->json(['message' => 'Origin not allowed.'], 403);
        }

        // Fetch read-only dashboard data
        $dashboard = Dashboard::with('widgets')->findOrFail($id);

        $widgetData = [];
        foreach ($dashboard->widgets as $widget) {
            $widgetData[] = [
                'widget_id' => $widget->id,
                'title'     => $widget->title ?? $widget->name ?? "Widget {$widget->id}",
                'type'      => $widget->type ?? 'unknown',
                'config'    => $widget->config ?? [],
            ];
        }

        $response = response()->json([
            'dashboard' => [
                'id'          => $dashboard->id,
                'name'        => $dashboard->name,
                'description' => $dashboard->description,
            ],
            'widgets'   => $widgetData,
            'embed'     => [
                'read_only' => true,
                'scope'     => 'embed:read',
            ],
        ]);

        // Attach CORS header so the iframe can read the response
        if ($corsOk && $origin !== '') {
            $response->header('Access-Control-Allow-Origin', $origin);
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // DELETE /bi/embed/tokens/{jti}  (auth required)
    // -------------------------------------------------------------------------

    /**
     * Revoke an embed token by its JTI.
     */
    public function revokeToken(Request $request, string $jti): JsonResponse
    {
        $user = $request->user();
        if (! $user->can('create', Dashboard::class)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $revoked = $this->embedService->revokeToken($jti);

        return response()->json(['revoked' => $revoked]);
    }
}
