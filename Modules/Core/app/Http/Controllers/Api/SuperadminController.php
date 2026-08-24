<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantAuditLog;
use Modules\Core\Services\OnboardingService;
use Modules\Core\Services\SmartDefaultsService;
use Modules\Core\Services\TenantManagerService;

/**
 * SuperadminController — Phase 40
 *
 * Full multi-tenant management portal for platform administrators.
 * All routes are protected by  auth:sanctum + role:super-admin.
 *
 * @group Superadmin — Multi-Tenant Portal
 */
class SuperadminController extends Controller
{
    public function __construct(
        private readonly TenantManagerService $manager,
        private readonly OnboardingService    $onboarding,
        private readonly SmartDefaultsService $smartDefaults,
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // TENANT CRUD
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * List all tenants (paginated, filterable).
     *
     * GET /api/v1/superadmin/tenants
     *
     * Query params:
     *  - search (string)   — filter by company name, slug, email
     *  - status (string)   — trial|active|suspended|cancelled
     *  - plan (string)     — starter|professional|enterprise|custom
     *  - country (string)  — ISO alpha-2
     *  - industry (string) — textile|construction|…
     *  - per_page (int)    — default 25
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::withTrashed()->with('owner:id,name,email');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search): void {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%")
                  ->orWhere('legal_name', 'like', "%{$search}%");
            });
        }

        foreach (['status', 'plan', 'country_code', 'industry'] as $filter) {
            $param = match ($filter) {
                'country_code' => $request->string('country')->toString(),
                default        => $request->string($filter)->toString(),
            };
            if ($param) {
                $query->where($filter, $param);
            }
        }

        $tenants = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($tenants);
    }

    /**
     * Provision a brand-new tenant.
     *
     * POST /api/v1/superadmin/tenants
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'legal_name'   => ['sometimes', 'string', 'max:255'],
            'company_type' => ['sometimes', 'string', 'in:sarl,sa,sas,snc,cooperative,ngo,individual'],
            'country_code' => ['required', 'string', 'size:2'],
            'currency'     => ['sometimes', 'string', 'size:3'],
            'locale'       => ['sometimes', 'string', 'in:fr,en,ar,sw,mg,ha,zh,hi,es,pt'],
            'timezone'     => ['sometimes', 'string', 'max:60'],
            'industry'     => ['required', 'string', 'in:textile,construction,commerce,agriculture,services,tech,healthcare,education,ngo,general'],
            'plan'         => ['required', 'string', 'in:starter,professional,enterprise,custom'],
            'owner_email'  => ['required', 'email', 'max:255'],
            'owner_name'   => ['required', 'string', 'max:255'],
            'owner_password'  => ['sometimes', 'string', 'min:8'],
            'contact_phone'   => ['sometimes', 'string', 'max:30'],
            'trial_days'      => ['sometimes', 'integer', 'min:0', 'max:90'],
        ]);

        $tenant = $this->manager->provision($validated);

        return response()->json([
            'message' => 'Tenant provisionné avec succès.',
            'tenant'  => $tenant,
        ], 201);
    }

    /**
     * Show full tenant detail.
     *
     * GET /api/v1/superadmin/tenants/{id}
     */
    public function show(string $id): JsonResponse
    {
        $tenant = $this->findOrFail($id);

        return response()->json([
            'tenant'           => $tenant->load('owner:id,name,email'),
            'onboarding_status'=> $this->onboarding->getStatus($tenant),
            'modules'          => \Illuminate\Support\Facades\DB::table('tenant_modules')
                ->where('tenant_id', $id)
                ->orderBy('module')
                ->get(),
        ]);
    }

    /**
     * Update a tenant's basic attributes.
     *
     * PUT /api/v1/superadmin/tenants/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = $this->findOrFail($id);

        $validated = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'legal_name'    => ['sometimes', 'string', 'max:255'],
            'company_type'  => ['sometimes', 'string', 'in:sarl,sa,sas,snc,cooperative,ngo,individual'],
            'country_code'  => ['sometimes', 'string', 'size:2'],
            'currency'      => ['sometimes', 'string', 'size:3'],
            'locale'        => ['sometimes', 'string'],
            'timezone'      => ['sometimes', 'string'],
            'industry'      => ['sometimes', 'string'],
            'contact_email' => ['sometimes', 'email'],
            'contact_phone' => ['sometimes', 'string', 'max:30'],
            'logo_url'      => ['sometimes', 'url', 'max:500'],
            'primary_color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings'      => ['sometimes', 'array'],
        ]);

        if (isset($validated['name'])) {
            $validated['company_name'] = $validated['name'];
        }

        TenantAuditLog::log($id, 'updated', [
            'old_values' => $tenant->only(array_keys($validated)),
            'new_values' => $validated,
        ]);

        $tenant->update($validated);

        return response()->json(['message' => 'Tenant mis à jour.', 'tenant' => $tenant->fresh()]);
    }

    /**
     * Suspend a tenant (access blocked, data preserved).
     *
     * POST /api/v1/superadmin/tenants/{id}/suspend
     */
    public function suspend(Request $request, string $id): JsonResponse
    {
        $tenant = $this->findOrFail($id);

        $validated = $request->validate([
            'reason' => ['sometimes', 'string', 'max:500'],
        ]);

        $this->manager->suspend($tenant, $validated['reason'] ?? '');

        return response()->json(['message' => 'Tenant suspendu.']);
    }

    /**
     * Reactivate a suspended tenant.
     *
     * POST /api/v1/superadmin/tenants/{id}/reactivate
     */
    public function reactivate(string $id): JsonResponse
    {
        $tenant = $this->findOrFail($id);
        $this->manager->reactivate($tenant);

        return response()->json(['message' => 'Tenant réactivé.']);
    }

    /**
     * Upgrade (or change) the tenant's plan.
     *
     * POST /api/v1/superadmin/tenants/{id}/upgrade-plan
     */
    public function upgradePlan(Request $request, string $id): JsonResponse
    {
        $tenant = $this->findOrFail($id);

        $validated = $request->validate([
            'plan' => ['required', 'string', 'in:starter,professional,enterprise,custom'],
        ]);

        $this->manager->upgradePlan($tenant, $validated['plan']);

        return response()->json([
            'message' => 'Plan mis à jour.',
            'tenant'  => $tenant->fresh(),
        ]);
    }

    /**
     * Export all tenant data (GDPR portability).
     *
     * GET /api/v1/superadmin/tenants/{id}/export
     */
    public function exportData(string $id): JsonResponse
    {
        $tenant = $this->findOrFail($id);
        $url    = $this->manager->exportData($tenant);

        return response()->json([
            'message'    => 'Export généré.',
            'export_url' => $url,
        ]);
    }

    /**
     * Permanently purge a tenant (GDPR right to erasure). Irreversible.
     *
     * DELETE /api/v1/superadmin/tenants/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $tenant = $this->findOrFail($id);
        $this->manager->purge($tenant);

        return response()->json(null, 204);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GLOBAL STATS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Global platform statistics for the superadmin dashboard.
     *
     * GET /api/v1/superadmin/stats
     */
    public function stats(): JsonResponse
    {
        return response()->json($this->manager->getGlobalStats());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AUDIT LOG
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Global audit log across all tenants.
     *
     * GET /api/v1/superadmin/audit-log
     *
     * Query params: tenant_id, action, user_id, from, to, per_page
     */
    public function auditLog(Request $request): JsonResponse
    {
        $query = TenantAuditLog::with('user:id,name,email')
            ->orderByDesc('created_at');

        if ($tenantId = $request->string('tenant_id')->toString()) {
            $query->where('tenant_id', $tenantId);
        }
        if ($action = $request->string('action')->toString()) {
            $query->where('action', $action);
        }
        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($from = $request->string('from')->toString()) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $query->where('created_at', '<=', $to);
        }

        return response()->json($query->paginate($request->integer('per_page', 50)));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SMART DEFAULTS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return smart defaults for a given country + industry.
     *
     * GET /api/v1/core/smart-defaults?country=SN&industry=textile
     */
    public function smartDefaultsEndpoint(Request $request): JsonResponse
    {
        $country  = strtoupper($request->string('country', 'SN')->toString());
        $industry = $request->string('industry', 'general')->toString();

        $defaults = $this->manager->getSmartDefaults($country, $industry);

        return response()->json([
            'country'  => $country,
            'industry' => $industry,
            'defaults' => $defaults,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ONBOARDING ROUTES (tenant-scoped, not superadmin)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Get onboarding status for the current user's tenant.
     *
     * GET /api/v1/onboarding/status
     */
    public function onboardingStatus(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        return response()->json($this->onboarding->getStatus($tenant));
    }

    /**
     * Complete onboarding step N.
     *
     * POST /api/v1/onboarding/step/{n}
     */
    public function onboardingStep(Request $request, int $n): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        $result = match ($n) {
            1 => $this->onboarding->completeStep1($tenant, $request->all()),
            2 => $this->onboarding->completeStep2($tenant, $request->all()),
            3 => $this->onboarding->completeStep3($tenant, (array) $request->input('modules', [])),
            4 => $this->onboarding->completeStep4($tenant, (array) $request->input('invitations', [])),
            5 => $this->onboarding->completeStep5($tenant, (array) $request->input('template_ids', [])),
            default => null,
        };

        if ($result === null) {
            return response()->json(['message' => "Étape {$n} invalide (1–5 acceptés)."], 422);
        }

        return response()->json($result);
    }

    /**
     * Skip all remaining onboarding steps.
     *
     * POST /api/v1/onboarding/skip
     */
    public function onboardingSkip(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->onboarding->skip($tenant);

        return response()->json([
            'message'        => 'Onboarding passé. Bienvenue sur votre tableau de bord.',
            'completion_pct' => $this->onboarding->getCompletionPercentage($tenant),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private function findOrFail(string $id): Tenant
    {
        $tenant = Tenant::withTrashed()->find($id);

        if (! $tenant) {
            abort(404, 'Tenant introuvable.');
        }

        return $tenant;
    }

    /**
     * Resolve the current user's active tenant via the real TenantUser
     * pivot — never a client-supplied value.
     *
     * Chantier 32.1: this used to accept a client-controlled ?tenant_id=
     * query param or X-Tenant-Id header FIRST, before ever consulting the
     * real TenantUser pivot — the exact cross-tenant IDOR pattern already
     * documented and fixed repeatedly elsewhere in this app this session
     * (Setup/AI/Achats/Integration/Workflow/Payroll/etc): any authenticated
     * user could read (onboarding/status), and — more severely — WRITE
     * another tenant's onboarding progress (onboarding/step/{n},
     * onboarding/skip) just by setting that header, since neither of these
     * 3 routes has a role: gate beyond auth:sanctum. Fixed by dropping the
     * header/query-param fallback entirely, matching the established
     * fix pattern — the TenantUser pivot lookup is the only real, non-
     * spoofable source for "which tenant does this authenticated user
     * belong to".
     */
    private function resolveTenant(Request $request): Tenant
    {
        $userId = $request->user()?->id;

        if ($userId) {
            $tenantUser = \Modules\Core\Models\TenantUser::where('user_id', $userId)->first();
            if ($tenantUser) {
                return Tenant::findOrFail($tenantUser->tenant_id);
            }
        }

        abort(404, 'Aucun tenant trouvé pour cet utilisateur.');
    }
}
