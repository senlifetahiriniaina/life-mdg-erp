<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\Sandbox;
use Modules\Core\Services\SandboxService;

/**
 * SandboxController — Tenant Sandbox Environments (Item #19)
 *
 * REST endpoints:
 *   POST   /api/v1/core/sandboxes             — create sandbox (clones tenant)
 *   GET    /api/v1/core/sandboxes             — list sandboxes for current tenant
 *   DELETE /api/v1/core/sandboxes/{id}        — soft-delete sandbox
 *   POST   /api/v1/core/sandboxes/{id}/reset  — reset + extend expiry
 */
class SandboxController extends Controller
{
    public function __construct(
        private readonly SandboxService $sandboxService,
    ) {}

    // ── POST /core/sandboxes ──────────────────────────────────────────────────

    /**
     * Clone the specified tenant into a fresh sandbox.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
        ]);

        $sandbox = $this->sandboxService->cloneTenant(
            $validated['tenant_id'],
            $validated['name'],
        );

        return response()->json([
            'data'    => $sandbox->load('tenant', 'parentTenant'),
            'message' => 'Sandbox created successfully.',
        ], 201);
    }

    // ── GET /core/sandboxes ───────────────────────────────────────────────────

    /**
     * List active sandboxes — every tenant's, since this endpoint is
     * super-admin-only, unless a specific parent_tenant_id is requested.
     *
     * Chantier 32.1: used to default to the phantom users.tenant_id column
     * (always null), which listByParent() then matched against an empty
     * string — the real frontend never passes parent_tenant_id at all, so
     * this always returned zero results regardless of real data. See
     * SandboxService::listAll()'s own docblock.
     */
    public function index(Request $request): JsonResponse
    {
        $parentTenantId = $request->query('parent_tenant_id');

        $sandboxes = $parentTenantId
            ? $this->sandboxService->listByParent((string) $parentTenantId)
            : $this->sandboxService->listAll();

        return response()->json(['data' => $sandboxes]);
    }

    // ── DELETE /core/sandboxes/{id} ───────────────────────────────────────────

    /**
     * Soft-delete and mark a sandbox as deleted.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->sandboxService->deleteSandbox($id);

        return response()->json(['message' => 'Sandbox deleted successfully.']);
    }

    // ── POST /core/sandboxes/{id}/reset ──────────────────────────────────────

    /**
     * Reset a sandbox: restore to active status and extend its expiry.
     */
    public function reset(int $id): JsonResponse
    {
        $sandbox = $this->sandboxService->resetSandbox($id);

        return response()->json([
            'data'    => $sandbox->load('tenant', 'parentTenant'),
            'message' => 'Sandbox reset successfully.',
        ]);
    }
}
