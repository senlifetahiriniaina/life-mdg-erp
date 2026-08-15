<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Integration\Models\WhbConnection;
use Modules\Integration\Services\WhbFederationService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies inbound WideHalo Bridge (WHB) federation requests using the
 * HMAC-SHA256 + timestamp scheme already implemented by
 * WhbFederationService::sign()/verify() for the outbound side.
 *
 * `federation.invite` is a deliberate exception: it is the very first call
 * made between two servers, before any shared secret exists to verify
 * against. It is bootstrap-trusted instead (HTTPS transport + a short-lived,
 * high-entropy, single-use invite_code) — the same trust model
 * WhbPartnerService already applies to the same-server invite flow. Every
 * other federation route only ever fires against a connection that was
 * already established via `invite`, so it always has a shared secret.
 */
class VerifyFederationSignature
{
    public function __construct(private readonly WhbFederationService $federation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->route()?->getName() === 'federation.invite') {
            return $next($request);
        }

        $instance  = $request->header('X-WH-Instance');
        $signature = $request->header('X-WH-Signature');
        $timestamp = $request->header('X-WH-Timestamp');

        if (! $instance || ! $signature || ! $timestamp || ! ctype_digit($timestamp)) {
            return response()->json(['error' => 'Missing or malformed federation signature headers.'], 401);
        }

        /** @var WhbConnection|null $connection */
        $connection = WhbConnection::where('remote_server_url', rtrim($instance, '/'))
            ->whereIn('status', ['pending', 'active'])
            ->whereNotNull('shared_secret')
            ->orderByDesc('id')
            ->first();

        if (! $connection) {
            return response()->json(['error' => 'Unknown federation partner.'], 401);
        }

        $secret = $this->federation->resolveSecret($connection);

        if (! $this->federation->verify($request->getContent(), $signature, $secret, (int) $timestamp)) {
            return response()->json(['error' => 'Invalid federation signature.'], 401);
        }

        $request->attributes->set('whb_connection', $connection);

        return $next($request);
    }
}
