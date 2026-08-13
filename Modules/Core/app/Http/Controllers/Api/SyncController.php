<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Services\SyncService;

/**
 * @group Core - Sync
 *
 * Offline-first data synchronisation (push/pull mutations).
 */
class SyncController extends Controller
{
    public function __construct(private readonly SyncService $syncService)
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Push offline mutations from client to server.
     */
    public function push(Request $request): JsonResponse
    {
        $request->validate([
            'mutations' => 'required|array',
            'mutations.*.id' => 'required|uuid',
            'mutations.*.entity_type' => 'required|string',
            'mutations.*.operation' => 'required|in:create,update,delete',
            'mutations.*.payload' => 'present|array',
            'mutations.*.client_timestamp' => 'required|date',
        ]);

        $result = $this->syncService->push($request->user()->id, $request->mutations);

        return response()->json($result);
    }

    /**
     * Pull server changes since last sync.
     */
    public function pull(Request $request): JsonResponse
    {
        $request->validate([
            'last_sync_at' => 'nullable|date',
        ]);

        $result = $this->syncService->pull($request->user()->id, $request->last_sync_at);

        return response()->json($result);
    }
}
