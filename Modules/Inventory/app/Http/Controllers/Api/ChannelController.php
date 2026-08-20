<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Jobs\SyncChannelJob;
use Modules\Inventory\Models\MarketplaceChannel;
use Modules\Inventory\Services\Marketplace\ChannelSyncService;

class ChannelController extends Controller
{
    public function __construct(private ChannelSyncService $channelSyncService) {}

    /**
     * Chantier 19 Lot 4: this whole controller filtered/wrote the phantom
     * `users.tenant_id` column (real DB column, never in User::$fillable,
     * never populated by the real registration flow — the exact bug class
     * already fixed repeatedly in Reporting/Strategy/AI/Sales/Achats/
     * Integration/Workflow/Setup/Payroll/Timesheets/…) — every company's
     * marketplace channel connections silently collapsed into one shared
     * tenant_id=0 bucket. `marketplace_channels` also carries a *second*,
     * separate `company_id` column that `connect()` previously accepted
     * straight from client-supplied request input and wrote unvalidated
     * (a real IDOR: any caller could tag a new channel with any company_id)
     * — both columns are now derived server-side from the real boundary,
     * `users.company_id`, and kept in sync rather than trusting either the
     * phantom user column or the client body.
     */
    private function tenantId(Request $request): int
    {
        return (int) ($request->user()?->company_id ?? 0);
    }

    public function index(Request $request): JsonResponse
    {
        $channels = MarketplaceChannel::where('company_id', $this->tenantId($request))->get();

        return response()->json([
            'data' => $channels,
        ]);
    }

    public function connect(Request $request, string $type): JsonResponse
    {
        if (! in_array($type, MarketplaceChannel::TYPES, true)) {
            return response()->json(['error' => "Unknown channel type: {$type}"], 422);
        }

        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'config' => ['required', 'array'],
        ]);

        $tenantId = $this->tenantId($request);

        $channel = MarketplaceChannel::create([
            'tenant_id'  => $tenantId,
            'type'       => $type,
            'name'       => $validated['name'],
            'config'     => $validated['config'],
            'status'     => 'inactive',
            'company_id' => $tenantId,
        ]);

        return response()->json(['data' => $channel], 201);
    }

    public function sync(Request $request, int $id): JsonResponse
    {
        $channel = MarketplaceChannel::where('company_id', $this->tenantId($request))->findOrFail($id);

        SyncChannelJob::dispatch($channel->id, $channel->type);

        return response()->json([
            'message'    => 'Sync job dispatched',
            'channel_id' => $id,
        ]);
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $channel   = MarketplaceChannel::where('company_id', $this->tenantId($request))->findOrFail($id);
        $connector = $this->channelSyncService->makeConnector($channel->type, $channel->config ?? []);

        return response()->json([
            'data' => array_merge(
                $connector->getStatus(),
                [
                    'channel_id'    => $channel->id,
                    'name'          => $channel->name,
                    'status'        => $channel->status,
                    'last_synced_at' => $channel->last_synced_at,
                    'sync_stats'    => $channel->sync_stats,
                ]
            ),
        ]);
    }
}
