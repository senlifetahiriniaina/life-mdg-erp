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

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id ?? 0;
        $channels = MarketplaceChannel::where('tenant_id', $tenantId)->get();

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
            'name'       => ['required', 'string', 'max:255'],
            'config'     => ['required', 'array'],
            'company_id' => ['required', 'integer'],
        ]);

        $tenantId = $request->user()?->tenant_id ?? 0;

        $channel = MarketplaceChannel::create([
            'tenant_id'  => $tenantId,
            'type'       => $type,
            'name'       => $validated['name'],
            'config'     => $validated['config'],
            'status'     => 'inactive',
            'company_id' => $validated['company_id'],
        ]);

        return response()->json(['data' => $channel], 201);
    }

    public function sync(int $id): JsonResponse
    {
        $channel = MarketplaceChannel::findOrFail($id);

        SyncChannelJob::dispatch($channel->id, $channel->type);

        return response()->json([
            'message'    => 'Sync job dispatched',
            'channel_id' => $id,
        ]);
    }

    public function status(int $id): JsonResponse
    {
        $channel   = MarketplaceChannel::findOrFail($id);
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
