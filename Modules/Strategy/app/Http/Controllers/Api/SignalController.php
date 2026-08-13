<?php

namespace Modules\Strategy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Strategy\Models\StrategySignal;
use Modules\Strategy\Services\SignalEngineService;

class SignalController extends Controller
{
    public function __construct(private SignalEngineService $service) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-Id', $request->query('tenant_id', 'default'));

        $query = StrategySignal::forTenant($tenantId)->active();

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $signals = $query->orderBy('detected_at', 'desc')->paginate(30);

        return response()->json($signals);
    }

    public function refresh(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-Id', 'default');

        $this->service->refreshSignals($tenantId);

        return response()->json(['message' => 'Signals refreshed.']);
    }

    public function markRead(int $id): JsonResponse
    {
        $signal = StrategySignal::findOrFail($id);
        $signal->update(['is_read' => true]);

        return response()->json(['message' => 'Signal marked as read.', 'id' => $id]);
    }

    public function dismiss(int $id): JsonResponse
    {
        $signal = StrategySignal::findOrFail($id);
        $signal->update(['is_dismissed' => true]);

        return response()->json(['message' => 'Signal dismissed.', 'id' => $id]);
    }
}
