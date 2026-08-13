<?php

declare(strict_types=1);

namespace Modules\Setup\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Setup\Services\ModuleManagerService;
use Modules\Setup\Services\SetupWizardService;
use Throwable;

/**
 * SetupWizardController
 *
 * Handles the 6-step company onboarding wizard API.
 * Routes prefix: /api/v1/setup/wizard
 *
 * Step 1 → POST /company        — company info
 * Step 2 → POST /admin          — admin profile
 * Step 3 → POST /modules        — module selection
 * Step 4 → POST /workflows      — workflow config
 * Step 5 → POST /apps           — app config
 * Step 6 → POST /complete       — finalize wizard
 */
class SetupWizardController extends Controller
{
    public function __construct(
        private readonly SetupWizardService  $wizardService,
        private readonly ModuleManagerService $moduleManager,
    ) {}

    // -----------------------------------------------------------------------
    // GET /wizard/state
    // -----------------------------------------------------------------------

    public function getState(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? 'default';

        $state = $this->wizardService->getState($tenantId);

        return response()->json([
            'success' => true,
            'data'    => $state,
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /wizard/company  — Step 1
    // -----------------------------------------------------------------------

    public function saveCompany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name'      => 'required|string|max:255',
            'legal_name'        => 'nullable|string|max:255',
            'company_type'      => ['nullable', Rule::in(['sarl', 'sa', 'sas', 'spa', 'gmbh', 'ltd', 'plc', 'other'])],
            'industry'          => 'nullable|string|max:100',
            'country_code'      => 'required|string|size:2',
            'currency_code'     => 'nullable|string|size:3',
            'timezone'          => 'nullable|string|max:100',
            'fiscal_year_start' => 'nullable|integer|min:1|max:12',
            'phone'             => 'nullable|string|max:50',
            'email'             => 'nullable|email|max:255',
            'website'           => 'nullable|url|max:255',
            'address'           => 'nullable|string|max:500',
            'city'              => 'nullable|string|max:100',
            'postal_code'       => 'nullable|string|max:20',
            'vat_number'        => 'nullable|string|max:50',
        ]);

        $tenantId = $request->user()->tenant_id ?? 'default';

        $profile = $this->wizardService->saveCompany($tenantId, $validated);

        return response()->json([
            'success' => true,
            'step'    => 1,
            'data'    => $profile,
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /wizard/admin  — Step 2
    // -----------------------------------------------------------------------

    public function saveAdmin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'nullable|string|max:255',
            'locale'   => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:100',
        ]);

        $tenantId = $request->user()->tenant_id ?? 'default';
        $userId   = $request->user()->id;

        $this->wizardService->saveAdmin($tenantId, $validated, $userId);

        return response()->json([
            'success' => true,
            'step'    => 2,
            'data'    => ['message' => 'Admin profile saved.'],
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /wizard/modules  — Step 3
    // -----------------------------------------------------------------------

    public function saveModules(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modules'   => 'required|array',
            'modules.*' => 'required|string|max:100',
        ]);

        $tenantId = $request->user()->tenant_id ?? 'default';

        $this->wizardService->saveModules($tenantId, $validated['modules']);

        return response()->json([
            'success' => true,
            'step'    => 3,
            'data'    => ['modules' => $validated['modules']],
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /wizard/workflows  — Step 4
    // -----------------------------------------------------------------------

    public function saveWorkflows(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'approval_required'      => 'nullable|boolean',
            'notification_channels'  => 'nullable|array',
            'notification_channels.*'=> 'string|in:email,sms,whatsapp,push',
        ]);

        $tenantId = $request->user()->tenant_id ?? 'default';

        $this->wizardService->saveWorkflows($tenantId, $validated);

        return response()->json([
            'success' => true,
            'step'    => 4,
            'data'    => $validated,
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /wizard/apps  — Step 5
    // -----------------------------------------------------------------------

    public function saveApps(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'apps'              => 'required|array',
            'apps.*'            => 'boolean',
        ]);

        // Validate app name keys
        $allowed = ['webapp', 'webapp-ecommerce', 'mobile', 'api'];
        foreach (array_keys($validated['apps']) as $appName) {
            if (!in_array($appName, $allowed, true)) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid app name: {$appName}",
                ], 422);
            }
        }

        $tenantId = $request->user()->tenant_id ?? 'default';

        $this->wizardService->saveApps($tenantId, $validated['apps']);

        return response()->json([
            'success' => true,
            'step'    => 5,
            'data'    => $validated['apps'],
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /wizard/complete  — Step 6
    // -----------------------------------------------------------------------

    public function complete(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? 'default';

        try {
            $profile = $this->wizardService->complete($tenantId);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot complete wizard: company profile not found. Please complete step 1 first.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'step'    => 6,
            'data'    => $profile,
        ]);
    }

    // -----------------------------------------------------------------------
    // GET /wizard/modules/catalog
    // -----------------------------------------------------------------------

    public function getModuleCatalog(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? 'default';

        $catalog = $this->moduleManager->getAll($tenantId);

        return response()->json([
            'success' => true,
            'data'    => $catalog,
        ]);
    }
}
