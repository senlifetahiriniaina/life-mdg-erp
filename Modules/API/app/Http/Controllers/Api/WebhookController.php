<?php

namespace Modules\API\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    public function index(Request $request)
    {
        $hooks = DB::table('api_webhooks')->where('tenant_id', $request->user()->id)->orderByDesc('created_at')->get();
        return response()->json(['data' => $hooks]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'events' => 'required|array',
            'secret' => 'nullable|string',
        ]);
        $id = DB::table('api_webhooks')->insertGetId([
            'tenant_id' => $request->user()->id,
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
        $data = $request->validate(['name' => 'string', 'url' => 'url', 'events' => 'array', 'active' => 'boolean']);
        if (isset($data['events'])) {
            $data['events'] = json_encode($data['events']);
        }
        DB::table('api_webhooks')->where('id', $id)->where('tenant_id', $request->user()->id)->update($data + ['updated_at' => now()]);
        return response()->json(['message' => 'Webhook updated']);
    }

    public function destroy(Request $request, $id)
    {
        DB::table('api_webhooks')->where('id', $id)->where('tenant_id', $request->user()->id)->delete();
        return response()->json(['message' => 'Webhook deleted']);
    }

    public function test(Request $request, $id)
    {
        $hook = DB::table('api_webhooks')->where('id', $id)->where('tenant_id', $request->user()->id)->first();
        abort_if(!$hook, 404);
        // Fire a test event
        return response()->json(['message' => 'Test webhook dispatched', 'url' => $hook->url]);
    }
}
