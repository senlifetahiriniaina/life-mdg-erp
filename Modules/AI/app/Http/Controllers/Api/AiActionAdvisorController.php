<?php

declare(strict_types=1);

namespace Modules\AI\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\AI\Models\AiUsageLimit;
use Modules\AI\Services\AiActionAdvisorService;
use Modules\AI\Services\AiUsageBudgetService;

class AiActionAdvisorController extends Controller
{
    public function __construct(
        private readonly AiActionAdvisorService $advisor,
        private readonly AiUsageBudgetService   $budget,
    ) {}

    // -------------------------------------------------------------------------
    // POST /api/v1/ai/advise
    // -------------------------------------------------------------------------

    /**
     * Get AI-generated advice before executing a risky action.
     * Also checks the user's AI usage budget.
     */
    public function advise(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module'   => ['required', 'string', 'max:64'],
            'action'   => ['required', 'string', 'max:128'],
            'context'  => ['sometimes', 'array'],
            'locale'   => ['sometimes', 'string', 'max:8'],
        ]);

        $locale   = $validated['locale']  ?? 'fr';
        $context  = $validated['context'] ?? [];
        $userId   = (int) $request->user()->id;
        $tenantId = (int) ($request->user()->tenant_id ?? $request->user()->id);
        $userRole = $request->user()->role ?? 'user';

        // Check budget
        $budgetStatus = $this->budget->checkLimit($userId, $tenantId);

        if (!$budgetStatus['allowed']) {
            return response()->json([
                'error'  => 'ai_budget_exceeded',
                'message' => 'Your AI usage limit has been reached for this period.',
                'budget' => [
                    'remaining_usd'    => $budgetStatus['remaining_usd'],
                    'remaining_tokens' => $budgetStatus['remaining_tokens'],
                    'usage_pct'        => $budgetStatus['usage_pct'],
                    'limit_exceeded'   => true,
                ],
            ], 429);
        }

        // Get advice
        $advice = $this->advisor->advise(
            module:   $validated['module'],
            action:   $validated['action'],
            context:  $context,
            locale:   $locale,
            userRole: $userRole,
        );

        // Log usage (estimate tokens: ~200 input, ~300 output for advise calls)
        if ($advice['enabled']) {
            $this->budget->logUsage(
                userId:       $userId,
                tenantId:     $tenantId,
                module:       $validated['module'],
                action:       $validated['action'],
                tokensIn:     200,
                tokensOut:    300,
                model:        'claude-sonnet-4-6',
                endpointType: 'advise',
                contextSummary: ['module' => $validated['module'], 'action' => $validated['action']],
            );

            // Refresh budget after logging
            $budgetStatus = $this->budget->checkLimit($userId, $tenantId);
        }

        return response()->json([
            'advice' => $advice,
            'budget' => [
                'remaining_usd'    => $budgetStatus['remaining_usd'] === PHP_FLOAT_MAX ? null : $budgetStatus['remaining_usd'],
                'remaining_tokens' => $budgetStatus['remaining_tokens'] === PHP_INT_MAX  ? null : $budgetStatus['remaining_tokens'],
                'usage_pct'        => $budgetStatus['usage_pct'],
                'limit_exceeded'   => false,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/ai/usage/me
    // -------------------------------------------------------------------------

    /**
     * Return the authenticated user's AI usage for the current period.
     */
    public function myUsage(Request $request): JsonResponse
    {
        $userId   = (int) $request->user()->id;
        $tenantId = (int) ($request->user()->tenant_id ?? $request->user()->id);
        $period   = $request->query('period', 'monthly');

        if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) {
            $period = 'monthly';
        }

        $usage  = $this->budget->getUsage($userId, $tenantId, $period);
        $status = $this->budget->checkLimit($userId, $tenantId);
        $limit  = $status['limit'];

        return response()->json([
            'usage'  => $usage,
            'limit'  => $limit ? [
                'limit_type'      => $limit->limit_type,
                'limit_value'     => $limit->limit_value,
                'period'          => $limit->period,
                'block_on_exceed' => $limit->block_on_exceed,
            ] : null,
            'budget' => [
                'allowed'          => $status['allowed'],
                'remaining_usd'    => $status['remaining_usd'] === PHP_FLOAT_MAX ? null : $status['remaining_usd'],
                'remaining_tokens' => $status['remaining_tokens'] === PHP_INT_MAX  ? null : $status['remaining_tokens'],
                'usage_pct'        => $status['usage_pct'],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/ai/admin/usage
    // -------------------------------------------------------------------------

    /**
     * Admin: get aggregated usage stats for the whole tenant.
     */
    public function adminUsage(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $tenantId = (int) ($request->user()->tenant_id ?? $request->user()->id);
        $period   = $request->query('period', 'monthly');
        $topN     = (int) ($request->query('limit', 10));

        if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) {
            $period = 'monthly';
        }

        $summary = $this->budget->getAdminSummary($tenantId, $period, max(1, min(100, $topN)));

        return response()->json($summary);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/ai/admin/limits
    // -------------------------------------------------------------------------

    /**
     * Admin: list all configured limits for the tenant.
     */
    public function adminListLimits(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $tenantId = (int) ($request->user()->tenant_id ?? $request->user()->id);

        $limits = AiUsageLimit::where('tenant_id', $tenantId)
            ->orderBy('user_id')
            ->get()
            ->map(fn (AiUsageLimit $l) => [
                'id'              => $l->id,
                'user_id'         => $l->user_id,
                'scope'           => $l->user_id ? 'user' : 'global',
                'limit_type'      => $l->limit_type,
                'limit_value'     => $l->limit_value,
                'period'          => $l->period,
                'block_on_exceed' => $l->block_on_exceed,
                'active'          => $l->active,
                'created_at'      => $l->created_at,
                'updated_at'      => $l->updated_at,
            ]);

        return response()->json(['limits' => $limits]);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/ai/admin/limits
    // -------------------------------------------------------------------------

    /**
     * Admin: create or update a usage limit.
     */
    public function adminSetLimit(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'user_id'         => ['nullable', 'integer'],
            'limit_type'      => ['required', 'string', 'in:tokens,usd'],
            'limit_value'     => ['required', 'numeric', 'min:0'],
            'period'          => ['required', 'string', 'in:daily,weekly,monthly'],
            'block_on_exceed' => ['sometimes', 'boolean'],
        ]);

        $tenantId     = (int) ($request->user()->tenant_id ?? $request->user()->id);
        $userId       = isset($validated['user_id']) ? (int) $validated['user_id'] : null;
        $blockOnExceed = (bool) ($validated['block_on_exceed'] ?? false);

        $limit = $this->budget->setLimit(
            tenantId:      $tenantId,
            userId:        $userId,
            limitType:     $validated['limit_type'],
            limitValue:    (float) $validated['limit_value'],
            period:        $validated['period'],
            blockOnExceed: $blockOnExceed,
        );

        return response()->json([
            'message' => 'Limit saved successfully.',
            'limit'   => $limit,
        ], 201);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/ai/admin/limits/{id}
    // -------------------------------------------------------------------------

    /**
     * Admin: remove a usage limit.
     */
    public function adminDeleteLimit(Request $request, int $id): JsonResponse
    {
        $this->requireAdmin($request);

        $tenantId = (int) ($request->user()->tenant_id ?? $request->user()->id);

        $limit = AiUsageLimit::where('tenant_id', $tenantId)->findOrFail($id);
        $limit->delete();

        return response()->json(['message' => 'Limit deleted successfully.']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Abort with 403 if the user does not have an admin/super-admin role.
     */
    private function requireAdmin(Request $request): void
    {
        $role = $request->user()->role ?? '';

        if (!in_array($role, ['admin', 'super-admin', 'super_admin'], true)) {
            abort(403, 'Admin access required.');
        }
    }
}
