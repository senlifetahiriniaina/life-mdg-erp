<?php

namespace Modules\API\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
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

    public function index(Request $request)
    {
        $keys = DB::table('api_keys')
            ->where('tenant_id', $this->tenantId($request))
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
            'expires_at' => 'nullable|date',
        ]);
        $rawKey = 'wh_' . Str::random(40);
        $id = DB::table('api_keys')->insertGetId([
            'tenant_id' => $this->tenantId($request),
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'key_hash' => Hash::make($rawKey),
            'key_prefix' => substr($rawKey, 0, 8),
            'scopes' => json_encode($data['permissions'] ?? ['read']),
            'rate_limit' => $data['rate_limit'] ?? 1000,
            'expires_at' => $data['expires_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['data' => ['id' => $id, 'key' => $rawKey, 'note' => 'Store this key — it will not be shown again']], 201);
    }

    public function show(Request $request, $id)
    {
        $key = DB::table('api_keys')->where('id', $id)->where('tenant_id', $this->tenantId($request))->first();
        abort_if(!$key, 404);
        return response()->json(['data' => $key]);
    }

    public function revoke(Request $request, $id)
    {
        // ApiKeyPolicy::delete() ignores $model entirely (role-only check, no
        // ownership comparison) — a transient instance satisfies the policy's
        // required 2-arg signature without an extra query for a role gate that
        // doesn't need one.
        $this->authorize('delete', new ApiKey());

        DB::table('api_keys')->where('id', $id)->where('tenant_id', $this->tenantId($request))->update(['revoked_at' => now()]);
        return response()->json(['message' => 'API key revoked']);
    }

    public function logs(Request $request, $id)
    {
        $logs = DB::table('api_requests')->where('api_key_id', $id)->where('tenant_id', $this->tenantId($request))->orderByDesc('created_at')->limit(100)->get();
        return response()->json(['data' => $logs]);
    }
}
