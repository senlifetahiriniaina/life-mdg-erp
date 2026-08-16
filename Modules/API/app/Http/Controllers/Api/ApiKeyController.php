<?php

namespace Modules\API\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ApiKeyController extends Controller
{
    public function index(Request $request)
    {
        $keys = DB::table('api_keys')
            ->where('tenant_id', $request->user()->id)
            ->whereNull('revoked_at')
            ->orderByDesc('created_at')
            ->get();
        return response()->json(['data' => $keys]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'rate_limit' => 'nullable|integer|min:1|max:10000',
            'expires_at' => 'nullable|date',
        ]);
        $rawKey = 'wh_' . Str::random(40);
        $id = DB::table('api_keys')->insertGetId([
            'tenant_id' => $request->user()->id,
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
        $key = DB::table('api_keys')->where('id', $id)->where('tenant_id', $request->user()->id)->first();
        abort_if(!$key, 404);
        return response()->json(['data' => $key]);
    }

    public function revoke(Request $request, $id)
    {
        DB::table('api_keys')->where('id', $id)->where('tenant_id', $request->user()->id)->update(['revoked_at' => now()]);
        return response()->json(['message' => 'API key revoked']);
    }

    public function logs(Request $request, $id)
    {
        $logs = DB::table('api_requests')->where('api_key_id', $id)->where('tenant_id', $request->user()->id)->orderByDesc('created_at')->limit(100)->get();
        return response()->json(['data' => $logs]);
    }
}
