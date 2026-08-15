<?php

namespace Modules\Integration\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Integration\Models\WhbConnection;
use Modules\Integration\Models\WhbExchange;

/**
 * Receiving side of the WideHalo Bridge (WHB) federation protocol.
 *
 * These endpoints are called by a partner's own WhbFederationService (see
 * that class's sendInvite()/sendAccept()/push()/refreshSession() methods)
 * — never by a browser or an authenticated in-app user. Every route except
 * `invite` is protected by VerifyFederationSignature, which resolves the
 * calling WhbConnection from the X-WH-Instance header and verifies the
 * X-WH-Signature/X-WH-Timestamp HMAC before the request reaches here; the
 * resolved connection is available via $request->attributes->get('whb_connection').
 */
class WhbFederationController extends Controller
{
    public function wellKnown(): JsonResponse
    {
        return response()->json([
            'name'    => config('app.name', 'WideHalo ERP'),
            'version' => config('app.version', '1.0.0'),
            'api'     => url('/api/v1'),
            'modules' => [],
        ]);
    }

    /**
     * POST /api/v1/federation/invite
     *
     * First contact between two servers — not signature-verified (see
     * VerifyFederationSignature's docblock for why). Provisions a new,
     * pending WhbConnection carrying the shared secret the inviter just
     * generated, so every later federation call from this partner CAN be
     * signature-verified.
     */
    public function receiveInvite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invite_code'        => ['required', 'string', 'size:8'],
            'local_tenant_name'  => ['required', 'string', 'max:255'],
            'initiator_server'   => ['required', 'url'],
            'expires_at'         => ['nullable', 'date'],
            'shared_secret'      => ['required', 'string', 'min:32'],
        ]);

        if (! str_starts_with($validated['initiator_server'], 'https://')) {
            return response()->json(['error' => 'initiator_server must use HTTPS.'], 422);
        }

        if (WhbConnection::where('invite_code', $validated['invite_code'])->exists()) {
            return response()->json(['error' => 'Invite code already known.'], 409);
        }

        $connection = WhbConnection::create([
            'local_tenant_id'    => 'default',
            'remote_server_url'  => rtrim($validated['initiator_server'], '/'),
            'remote_tenant_name' => $validated['local_tenant_name'],
            'connection_type'    => 'remote',
            'status'             => 'pending',
            'invite_code'        => $validated['invite_code'],
            'invite_expires_at'  => $validated['expires_at'] ?? now()->addHours(24),
            'shared_secret'      => encrypt($validated['shared_secret']),
        ]);

        return response()->json([
            'status'        => 'received',
            'connection_id' => $connection->id,
        ], 201);
    }

    /**
     * POST /api/v1/federation/accept
     *
     * Symmetric receiver for WhbFederationService::sendAccept() — the
     * partner we invited telling us who accepted, so our own pending row
     * (created not knowing that yet) can be filled in.
     */
    public function receiveAccept(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invite_code'        => ['required', 'string', 'size:8'],
            'remote_tenant_id'   => ['required', 'string', 'max:255'],
            'remote_tenant_name' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var WhbConnection|null $connection */
        $connection = WhbConnection::where('invite_code', $validated['invite_code'])->first();

        if (! $connection) {
            return response()->json(['error' => 'Unknown invite code.'], 404);
        }

        // The middleware already verified this request's signature against
        // *some* connection (by X-WH-Instance) — cross-check it's this one.
        // Otherwise a valid signature on connection A could be used to
        // hijack connection B's invite_code.
        $verified = $request->attributes->get('whb_connection');
        if (! $verified instanceof WhbConnection || $verified->id !== $connection->id) {
            return response()->json(['error' => 'Signature does not match this invite_code.'], 401);
        }

        $connection->update([
            'remote_tenant_id'   => $validated['remote_tenant_id'],
            'remote_tenant_name' => $validated['remote_tenant_name'] ?? $connection->remote_tenant_name,
        ]);

        return response()->json(['status' => 'acknowledged']);
    }

    /**
     * POST /api/v1/federation/exchange
     *
     * Symmetric receiver for WhbFederationService::push(). Mirrors
     * WhbPartnerService::createLocalInbound()'s same-server logic for the
     * genuinely cross-server case, where there is exactly one connection
     * row (the one the signature was verified against) rather than a
     * mirrored pair.
     */
    public function receiveExchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data_type' => ['required', 'string'],
            'payload'   => ['required', 'array'],
        ]);

        /** @var WhbConnection|null $connection */
        $connection = $request->attributes->get('whb_connection');

        if (! $connection instanceof WhbConnection || $connection->status !== 'active') {
            return response()->json(['error' => 'Connection is not active.'], 403);
        }

        $permission = $connection->permissions()->where('data_type', $validated['data_type'])->first();

        if (! $permission || ! $permission->can_receive) {
            return response()->json([
                'error' => "Not permitted to receive '{$validated['data_type']}' from this partner.",
            ], 403);
        }

        WhbExchange::create([
            'connection_id'       => $connection->id,
            'direction'           => 'inbound',
            'data_type'           => $validated['data_type'],
            'local_resource_type' => $validated['data_type'],
            'payload'             => $validated['payload'],
            'status'              => $permission->auto_accept ? 'accepted' : 'received',
            'processed_at'        => $permission->auto_accept ? now() : null,
        ]);

        $connection->touch('last_sync_at');

        return response()->json(['status' => 'received'], 201);
    }

    /**
     * POST /api/v1/federation/refresh
     *
     * Symmetric receiver for WhbFederationService::refreshSession().
     */
    public function receiveRefresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'new_token' => ['required', 'string'],
        ]);

        /** @var WhbConnection|null $connection */
        $connection = $request->attributes->get('whb_connection');

        if (! $connection instanceof WhbConnection) {
            return response()->json(['error' => 'Unknown federation partner.'], 401);
        }

        $connection->update([
            'session_token'      => encrypt($validated['new_token']),
            'session_expires_at' => now()->addHours(24),
        ]);

        return response()->json(['status' => 'refreshed']);
    }
}
