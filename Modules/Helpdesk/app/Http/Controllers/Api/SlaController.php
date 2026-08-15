<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Helpdesk\Models\SlaPolicy;
use Modules\Helpdesk\Services\SlaAutomationService;
use Modules\Helpdesk\Services\SlaService;

/**
 * @group Controllers - Sla
 *
 * Manage Sla resources.
 */
class SlaController extends Controller
{
    public function __construct(
        private readonly SlaAutomationService $service,
        private readonly SlaService $slaService,
    ) {}

    public function indexPolicies(): JsonResponse
    {
        if (SlaPolicy::count() === 0) {
            $this->slaService->seedDefaultPolicies();
        }

        $policies = SlaPolicy::where('is_active', true)->get();

        return response()->json($policies);
    }

    public function storePolicies(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|string|in:low,normal,medium,high,urgent,critical',
            'response_time_minutes' => 'required|integer|min:1',
            'resolution_time_minutes' => 'required|integer|min:1',
            'business_hours_only' => 'nullable|boolean',
            'escalation_enabled' => 'nullable|boolean',
            'escalation_after_minutes' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $policy = SlaPolicy::create($validated);

        return response()->json($policy, 201);
    }

    public function showPolicy(SlaPolicy $policy): JsonResponse
    {
        return response()->json($policy);
    }

    public function updatePolicy(Request $request, SlaPolicy $policy): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'sometimes|required|string|in:low,normal,medium,high,urgent,critical',
            'response_time_minutes' => 'sometimes|required|integer|min:1',
            'resolution_time_minutes' => 'sometimes|required|integer|min:1',
            'business_hours_only' => 'nullable|boolean',
            'escalation_enabled' => 'nullable|boolean',
            'escalation_after_minutes' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $policy->update($validated);

        return response()->json($policy);
    }

    public function deletePolicy(SlaPolicy $policy): JsonResponse
    {
        $policy->delete();

        return response()->json(null, 204);
    }

    public function checkBreaches(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticket_id' => 'required|integer',
            'priority' => 'required|string|in:low,normal,medium,high,urgent,critical',
            'created_at' => 'required|date',
            'first_response_at' => 'nullable|date',
            'resolved_at' => 'nullable|date',
        ]);

        $breaches = $this->service->checkAndRecordBreaches(
            ticketId: (int) $validated['ticket_id'],
            priority: $validated['priority'],
            createdAt: Carbon::parse($validated['created_at']),
            firstResponseAt: isset($validated['first_response_at'])
                ? Carbon::parse($validated['first_response_at'])
                : null,
            resolvedAt: isset($validated['resolved_at'])
                ? Carbon::parse($validated['resolved_at'])
                : null,
        );

        return response()->json($breaches);
    }

    public function pendingBreaches(): JsonResponse
    {
        return response()->json($this->service->getPendingBreaches());
    }

    public function ticketBreaches(int $ticketId): JsonResponse
    {
        return response()->json($this->service->getTicketBreaches($ticketId));
    }

    public function runEscalations(): JsonResponse
    {
        $count = $this->service->runEscalations();

        return response()->json(['escalated_count' => $count]);
    }

    public function complianceStats(): JsonResponse
    {
        return response()->json($this->service->getComplianceStats());
    }

    public function performanceReport(): JsonResponse
    {
        return response()->json($this->service->getPerformanceReport());
    }
}
