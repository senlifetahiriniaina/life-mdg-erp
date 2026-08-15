<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Services\TenantRegistrationService;

/**
 * @group Controllers - Tenant Registration
 *
 * Manage Tenant Registration resources.
 */
class TenantRegistrationController extends Controller
{
    public function __construct(
        private readonly TenantRegistrationService $service,
    ) {}

    /**
     * POST /api/v1/tenants/register
     * Public — no auth required.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'plan' => ['sometimes', 'string', 'in:starter,growth,enterprise'],
        ]);

        $result = $this->service->register(
            $validated['company_name'],
            $validated['email'],
            $validated['plan'] ?? 'starter',
        );

        return response()->json($result, 201);
    }

    /**
     * GET /api/v1/tenants/check-slug/{slug}
     * Public — no auth required.
     */
    public function checkSlug(string $slug): JsonResponse
    {
        $slug = Str::lower(trim($slug));
        $available = ! DB::table('tenants')->where('slug', $slug)->exists();

        $response = ['available' => $available];

        if (! $available) {
            // Suggest an alternative by appending a fresh random suffix
            $base = preg_replace('/-[a-z0-9]{8}$/', '', $slug) ?: $slug;
            $suggestion = $base.'-'.Str::lower(Str::random(8));

            // Ensure the suggestion is also unique
            while (DB::table('tenants')->where('slug', $suggestion)->exists()) {
                $suggestion = $base.'-'.Str::lower(Str::random(8));
            }

            $response['suggestion'] = $suggestion;
        }

        return response()->json($response);
    }

    /**
     * GET /api/v1/tenants/me
     * Requires auth:sanctum.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Resolve tenant_id: use tenancy tenant key if available, else fall back
        $tenantId = (string) (tenancy()->tenant?->getTenantKey() ?? $user->id);

        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        return response()->json([
            'tenant_id' => $tenant->id,
            'slug' => $tenant->slug,
            'domain' => $tenant->domain,
            'company_name' => $tenant->company_name,
            'plan' => $tenant->plan,
            'is_active' => (bool) $tenant->is_active,
        ]);
    }
}
