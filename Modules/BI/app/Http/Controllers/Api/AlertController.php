<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\BI\Models\BiAlert;
use Modules\BI\Services\AlertService;

/**
 * @group BI - Alerts
 *
 * Manage real-time BI alerts.
 */
class AlertController extends Controller
{
    public function __construct(private readonly AlertService $service) {}

    public function index(Request $request): JsonResponse
    {
        $alerts = BiAlert::with(['widget', 'biQuery'])
            ->latest()
            ->paginate(25);

        return response()->json($alerts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'widget_id' => ['nullable', 'integer', 'exists:bi_widgets,id'],
            'query_id' => ['nullable', 'integer', 'exists:bi_queries,id'],
            'condition_type' => ['required', 'string', 'in:above,below,equals,change_pct'],
            'threshold' => ['required', 'numeric'],
            'metric_name' => ['required', 'string', 'max:255'],
            'check_interval_minutes' => ['nullable', 'integer', 'min:1'],
            'channels' => ['required', 'array'],
            'channels.*' => ['string', 'in:email,slack,in_app'],
            'recipients' => ['required', 'array'],
            'recipients.*' => ['integer'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $alert = BiAlert::create($validated);

        return response()->json($alert->load(['widget', 'biQuery']), 201);
    }

    public function show(BiAlert $alert): JsonResponse
    {
        return response()->json($alert->load(['widget', 'biQuery', 'events' => fn ($q) => $q->latest()->limit(20)]));
    }

    public function update(Request $request, BiAlert $alert): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'widget_id' => ['nullable', 'integer', 'exists:bi_widgets,id'],
            'query_id' => ['nullable', 'integer', 'exists:bi_queries,id'],
            'condition_type' => ['sometimes', 'string', 'in:above,below,equals,change_pct'],
            'threshold' => ['sometimes', 'numeric'],
            'metric_name' => ['sometimes', 'string', 'max:255'],
            'check_interval_minutes' => ['nullable', 'integer', 'min:1'],
            'channels' => ['sometimes', 'array'],
            'channels.*' => ['string', 'in:email,slack,in_app'],
            'recipients' => ['sometimes', 'array'],
            'recipients.*' => ['integer'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $alert->update($validated);

        return response()->json($alert->fresh());
    }

    public function destroy(BiAlert $alert): JsonResponse
    {
        $alert->delete();

        return response()->json(null, 204);
    }

    /**
     * Manually trigger an alert check.
     */
    public function test(BiAlert $alert): JsonResponse
    {
        try {
            $triggered = $this->service->checkAlert($alert);

            return response()->json(['triggered' => $triggered, 'last_value' => $alert->fresh()?->last_value]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Alert check failed: '.$e->getMessage()], 500);
        }
    }
}
