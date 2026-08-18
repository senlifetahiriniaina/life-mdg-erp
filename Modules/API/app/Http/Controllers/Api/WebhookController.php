<?php

namespace Modules\API\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\API\Models\ApiWebhook;

class WebhookController extends Controller
{
    /**
     * Chantier 10: same fix as ApiKeyController::tenantId() — every scoping
     * site here used the acting user's own id, not the real company_id
     * tenant boundary.
     */
    private function tenantId(Request $request): int
    {
        return (int) ($request->user()->company_id ?? 0);
    }

    public function index(Request $request)
    {
        $hooks = DB::table('api_webhooks')->where('tenant_id', $this->tenantId($request))->orderByDesc('created_at')->get();
        return response()->json(['data' => $hooks]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', ApiWebhook::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'events' => 'required|array',
            'secret' => 'nullable|string',
        ]);
        $id = DB::table('api_webhooks')->insertGetId([
            'tenant_id' => $this->tenantId($request),
            'name' => $data['name'],
            'url' => $data['url'],
            'secret' => $data['secret'] ?? null,
            'events' => json_encode($data['events']),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['data' => ['id' => $id] + $data], 201);
    }

    public function update(Request $request, $id)
    {
        // WebhookPolicy::update()/delete() ignore $model entirely (role-only
        // check, no ownership comparison) — a transient instance satisfies the
        // policy's required 2-arg signature without an extra query for a role
        // gate that doesn't need one.
        $this->authorize('update', new ApiWebhook());

        $data = $request->validate(['name' => 'string', 'url' => 'url', 'events' => 'array', 'active' => 'boolean']);
        if (isset($data['events'])) {
            $data['events'] = json_encode($data['events']);
        }
        DB::table('api_webhooks')->where('id', $id)->where('tenant_id', $this->tenantId($request))->update($data + ['updated_at' => now()]);
        return response()->json(['message' => 'Webhook updated']);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorize('delete', new ApiWebhook());

        DB::table('api_webhooks')->where('id', $id)->where('tenant_id', $this->tenantId($request))->delete();
        return response()->json(['message' => 'Webhook deleted']);
    }

    public function test(Request $request, $id)
    {
        $hook = DB::table('api_webhooks')->where('id', $id)->where('tenant_id', $this->tenantId($request))->first();
        abort_if(!$hook, 404);
        // Fire a test event
        return response()->json(['message' => 'Test webhook dispatched', 'url' => $hook->url]);
    }
}
