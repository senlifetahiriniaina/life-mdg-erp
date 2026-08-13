<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $tenants = DB::table('tenants')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($tenants);
    }

    public function show(Request $request, string $tenant): JsonResponse
    {
        $this->authorizeAdmin($request);

        $record = DB::table('tenants')->where('id', $tenant)->first();

        if (! $record) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        return response()->json(['data' => $record]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'id'       => 'required|string|max:255|unique:tenants,id',
            'data'     => 'nullable|array',
        ]);

        DB::table('tenants')->insert([
            'id'         => $validated['id'],
            'data'       => isset($validated['data']) ? json_encode($validated['data']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('[TenantManagement] Tenant created', ['tenant_id' => $validated['id'], 'by' => $request->user()?->id]);

        return response()->json(['message' => 'Tenant created', 'id' => $validated['id']], 201);
    }

    public function update(Request $request, string $tenant): JsonResponse
    {
        $this->authorizeAdmin($request);

        $record = DB::table('tenants')->where('id', $tenant)->first();

        if (! $record) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        $validated = $request->validate(['data' => 'nullable|array']);

        DB::table('tenants')->where('id', $tenant)->update([
            'data'       => isset($validated['data']) ? json_encode($validated['data']) : $record->data,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Tenant updated']);
    }

    public function suspend(Request $request, string $tenant): JsonResponse
    {
        $this->authorizeAdmin($request);

        $affected = DB::table('tenants')->where('id', $tenant)->update([
            'suspended_at' => now(),
            'updated_at'   => now(),
        ]);

        if (! $affected) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        Log::warning('[TenantManagement] Tenant suspended', ['tenant_id' => $tenant, 'by' => $request->user()?->id]);

        return response()->json(['message' => 'Tenant suspended']);
    }

    public function activate(Request $request, string $tenant): JsonResponse
    {
        $this->authorizeAdmin($request);

        $affected = DB::table('tenants')->where('id', $tenant)->update([
            'suspended_at' => null,
            'updated_at'   => now(),
        ]);

        if (! $affected) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        Log::info('[TenantManagement] Tenant activated', ['tenant_id' => $tenant, 'by' => $request->user()?->id]);

        return response()->json(['message' => 'Tenant activated']);
    }

    public function destroy(Request $request, string $tenant): JsonResponse
    {
        $this->authorizeAdmin($request);

        $record = DB::table('tenants')->where('id', $tenant)->first();

        if (! $record) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        DB::table('tenants')->where('id', $tenant)->delete();

        Log::warning('[TenantManagement] Tenant deleted', ['tenant_id' => $tenant, 'by' => $request->user()?->id]);

        return response()->json(['message' => 'Tenant deleted']);
    }

    public function gdprPurge(Request $request, string $tenant): JsonResponse
    {
        $this->authorizeAdmin($request);

        $record = DB::table('tenants')->where('id', $tenant)->first();

        if (! $record) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        // Mark for GDPR purge — actual purge dispatched asynchronously
        DB::table('tenants')->where('id', $tenant)->update([
            'gdpr_purge_requested_at' => now(),
            'updated_at'              => now(),
        ]);

        Log::warning('[TenantManagement] GDPR purge requested', ['tenant_id' => $tenant, 'by' => $request->user()?->id]);

        return response()->json([
            'message'     => 'GDPR purge scheduled. All tenant data will be permanently erased.',
            'tenant_id'   => $tenant,
            'requested_at' => now()->toIso8601String(),
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user?->hasAnyRole(['super-admin', 'admin', 'system-admin'])) {
            abort(403, 'Forbidden');
        }
    }
}
