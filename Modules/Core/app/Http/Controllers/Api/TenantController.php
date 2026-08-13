<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Services\TenantProvisioningService;

/**
 * @group Tenant Management (super-admin)
 *
 * Full lifecycle management of tenants on the central database.
 * All endpoints require the `super-admin` role.
 */
class TenantController extends Controller
{
    public function __construct(private readonly TenantProvisioningService $service) {}

    /**
     * List all tenants (paginated).
     *
     * GET /api/v1/tenants
     * Query: search, plan, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $tenants = $this->service->list(
            perPage: $request->integer('per_page', 25),
            search: $request->string('search')->toString() ?: null,
            plan: $request->string('plan')->toString() ?: null,
        );

        return response()->json($tenants);
    }

    /**
     * Show a single tenant with its modules.
     *
     * GET /api/v1/tenants/{id}
     */
    public function show(string $id): JsonResponse
    {
        $tenant = $this->service->find($id);

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        return response()->json($tenant);
    }

    /**
     * Update a tenant's attributes (plan, company name, domain).
     *
     * PUT /api/v1/tenants/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->assertTenantExists($id);

        $validated = $request->validate([
            'company_name' => ['sometimes', 'string', 'max:255'],
            'plan' => ['sometimes', 'string', 'in:starter,growth,enterprise'],
            'domain' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->service->update($id, $validated);

        return response()->json(['message' => 'Tenant updated.']);
    }

    /**
     * Delete a tenant permanently (central DB only).
     *
     * DELETE /api/v1/tenants/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $this->assertTenantExists($id);
        $this->service->delete($id);

        return response()->json(null, 204);
    }

    /**
     * Suspend a tenant (is_active = false).
     *
     * POST /api/v1/tenants/{id}/suspend
     */
    public function suspend(string $id): JsonResponse
    {
        $this->assertTenantExists($id);
        $this->service->suspend($id);

        return response()->json(['message' => 'Tenant suspended.']);
    }

    /**
     * Activate a suspended tenant.
     *
     * POST /api/v1/tenants/{id}/activate
     */
    public function activate(string $id): JsonResponse
    {
        $this->assertTenantExists($id);
        $this->service->activate($id);

        return response()->json(['message' => 'Tenant activated.']);
    }

    /**
     * Re-run migrations and seeds for a tenant (idempotent).
     *
     * POST /api/v1/tenants/{id}/reprovision
     */
    public function reprovision(string $id): JsonResponse
    {
        $this->assertTenantExists($id);
        $this->service->reprovision($id);

        return response()->json(['message' => 'Reprovisioning dispatched.'], 202);
    }

    /**
     * List enabled/disabled state of all modules for a tenant.
     *
     * GET /api/v1/tenants/{id}/modules
     */
    public function modules(string $id): JsonResponse
    {
        $this->assertTenantExists($id);
        $modules = $this->service->getModules($id);

        return response()->json(['modules' => $modules]);
    }

    /**
     * Bulk-enable or disable modules for a tenant.
     *
     * PUT /api/v1/tenants/{id}/modules
     * Body: { "modules": { "CRM": true, "Manufacturing": false } }
     */
    public function updateModules(Request $request, string $id): JsonResponse
    {
        $this->assertTenantExists($id);

        $validated = $request->validate([
            'modules' => ['required', 'array'],
            'modules.*' => ['boolean'],
        ]);

        $this->service->setModules($id, $validated['modules']);

        return response()->json(['message' => 'Modules updated.']);
    }

    /**
     * Return usage statistics for a tenant.
     *
     * GET /api/v1/tenants/{id}/stats
     */
    public function stats(string $id): JsonResponse
    {
        $this->assertTenantExists($id);

        return response()->json($this->service->stats($id));
    }

    // ── Internal ────────────────────────────────────────────────────────────────

    private function assertTenantExists(string $id): void
    {
        if (! DB::table('tenants')->where('id', $id)->exists()) {
            abort(404, 'Tenant not found.');
        }
    }
}
