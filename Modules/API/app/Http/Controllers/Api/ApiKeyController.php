<?php

namespace Modules\API\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Modules\API\Models\ApiKey;

class ApiKeyController extends Controller
{
    /**
     * Chantier 10: every `tenant_id` scoping site in this controller used
     * $request->user()->id directly — not even the phantom tenant_id
     * column, just the acting user's own id — so api_keys/api_requests were
     * scoped per-user instead of per-company. Not a cross-tenant leak (no
     * shared/guessable fallback), but a real collaboration bug: two admins
     * of the same real company could never see/manage/revoke each other's
     * API keys. Fixed to the real tenant boundary, company_id
     * (api_keys.tenant_id is a real integer column).
     */
    private function tenantId(Request $request): int
    {
        return (int) ($request->user()->company_id ?? 0);
    }

    /**
     * Chantier 32.5: index()/show()/logs() previously queried api_keys via
     * DB::table(), returning raw stdClass rows — including the bcrypt
     * key_hash column in full — to the client. ApiKey::$hidden already
     * marks key_hash hidden, but that only applies to the Eloquent model;
     * a raw query builder result bypasses it entirely. Rewritten onto the
     * real Eloquent model (and its own, until-now-unused
     * scopeForTenant()/scopeActive()) so $hidden is genuinely enforced.
     */
    public function index(Request $request)
    {
        $keys = ApiKey::query()
            ->forTenant($this->tenantId($request))
            ->whereNull('revoked_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $keys]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', ApiKey::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'rate_limit' => 'nullable|integer|min:1|max:10000',
            // Chantier 32.5: a key that's already expired the moment it's
            // minted is never a legitimate use case — ApiKey::isActive()
            // would reject it on its very first real use, so validate the
            // business rule here instead of letting a caller silently
            // create a dead-on-arrival credential.
            'expires_at' => 'nullable|date|after:now',
        ]);

        $rawKey = 'wh_' . Str::random(40);

        $key = ApiKey::create([
            'tenant_id'   => $this->tenantId($request),
            'user_id'     => $request->user()->id,
            'name'        => $data['name'],
            'key_hash'    => Hash::make($rawKey),
            'key_prefix'  => substr($rawKey, 0, 8),
            'scopes'      => $data['permissions'] ?? ['read'],
            'rate_limit'  => $data['rate_limit'] ?? 1000,
            'expires_at'  => $data['expires_at'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'id'   => $key->id,
                'key'  => $rawKey,
                'note' => 'Store this key — it will not be shown again',
            ],
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $key = ApiKey::query()->forTenant($this->tenantId($request))->find($id);
        abort_if(! $key, 404);

        return response()->json(['data' => $key]);
    }

    public function revoke(Request $request, $id)
    {
        // ApiKeyPolicy::delete() ignores $model entirely (role-only check, no
        // ownership comparison) — a transient instance satisfies the policy's
        // required 2-arg signature without an extra query for a role gate that
        // doesn't need one.
        $this->authorize('delete', new ApiKey());

        // Chantier 32.5: instance-updated (find() then ->update()) rather
        // than a mass Builder::update() — a mass update never fires
        // Eloquent's updating/updated events, silently skipping the new
        // RecordsActivity audit-log entry a real credential revocation
        // should leave. Cross-tenant behavior unchanged: find() scoped to
        // this tenant returns null for another company's key id, so the
        // whole block is a silent no-op exactly like the previous
        // unscoped-match-count update was.
        $key = ApiKey::query()->forTenant($this->tenantId($request))->find($id);
        if ($key) {
            $key->update(['revoked_at' => now()]);
        }

        return response()->json(['message' => 'API key revoked']);
    }

    public function logs(Request $request, $id)
    {
        $logs = \Modules\API\Models\ApiRequest::query()
            ->forTenant($this->tenantId($request))
            ->where('api_key_id', $id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => $logs]);
    }

    /**
     * Chantier 32.5: the one real, self-contained consumer of
     * Modules\API\Http\Middleware\AuthenticateApiKey — proves the
     * previously-dead api_keys authentication pipeline genuinely works
     * end to end (real key resolved, real per-key rate limit enforced,
     * real api_requests row written) without retrofitting API-key auth
     * across this app's business endpoints, a separate, larger, future
     * decision (see the middleware's own docblock).
     */
    public function ping(Request $request)
    {
        /** @var ApiKey $key */
        $key = $request->attributes->get('api_key');

        return response()->json([
            'data' => [
                'authenticated' => true,
                'key_id'        => $key->id,
                'name'          => $key->name,
                'tenant_id'     => $key->tenant_id,
                'scopes'        => $key->scopes,
                'rate_limit_per_hour' => $key->rate_limit,
                'server_time'   => now()->toIso8601String(),
            ],
        ]);
    }
}
