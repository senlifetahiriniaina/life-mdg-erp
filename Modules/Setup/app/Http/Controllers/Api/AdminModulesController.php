<?php

declare(strict_types=1);

namespace Modules\Setup\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use InvalidArgumentException;
use Modules\Setup\Exceptions\ModuleDeactivationBlockedException;
use Modules\Setup\Services\ModuleManagerService;

/**
 * AdminModulesController
 *
 * Admin-only module management.
 * Routes prefix: /api/v1/admin/modules
 */
class AdminModulesController extends Controller
{
    public function __construct(private readonly ModuleManagerService $moduleManager) {}

    // -----------------------------------------------------------------------
    // GET /admin/modules
    // -----------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        // Chantier 10: was `tenant_id ?? 'default'` — the phantom tenant_id
        // column (see SetupController::tenantId()'s docblock in this same
        // module for the full "Chantier 10 correction" reasoning — a repo-
        // wide grep of every user-creation path confirms tenant_id is never
        // populated), so every admin landed in the same shared 'default'
        // module-config bucket regardless of real company. Fixed to the
        // real tenant boundary, company_id (cast to string —
        // ModuleManagerService keys on a string tenant id).
        $tenantId = (string) ($request->user()->company_id ?? 0);

        $modules = $this->moduleManager->getAll($tenantId);

        return response()->json([
            'success' => true,
            'data'    => $modules,
        ]);
    }

    // -----------------------------------------------------------------------
    // PUT /admin/modules/{module}
    // -----------------------------------------------------------------------

    public function update(Request $request, string $module): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        // Chantier 10: was `tenant_id ?? 'default'` — the phantom tenant_id
        // column (see SetupController::tenantId()'s docblock in this same
        // module for the full "Chantier 10 correction" reasoning — a repo-
        // wide grep of every user-creation path confirms tenant_id is never
        // populated), so every admin landed in the same shared 'default'
        // module-config bucket regardless of real company. Fixed to the
        // real tenant boundary, company_id (cast to string —
        // ModuleManagerService keys on a string tenant id).
        $tenantId = (string) ($request->user()->company_id ?? 0);
        $userId   = $request->user()->id;

        try {
            if ($validated['is_active']) {
                $config = $this->moduleManager->activate($module, $tenantId, $userId);

                return response()->json([
                    'success' => true,
                    'data'    => $config,
                ]);
            }

            $config = $this->moduleManager->deactivate($module, $tenantId, $userId);

            return response()->json([
                'success' => true,
                'data'    => $config,
            ]);
        } catch (ModuleDeactivationBlockedException $e) {
            return response()->json([
                'success'    => false,
                'message'    => $e->getMessage(),
                'dependents' => $e->getDependents(),
            ], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    // -----------------------------------------------------------------------
    // POST /admin/modules/bulk
    // -----------------------------------------------------------------------

    public function bulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modules'   => 'required|array|min:1',
            'modules.*' => 'required|string|max:100',
            'action'    => 'required|in:activate,deactivate',
        ]);

        // Chantier 10: was `tenant_id ?? 'default'` — the phantom tenant_id
        // column (see SetupController::tenantId()'s docblock in this same
        // module for the full "Chantier 10 correction" reasoning — a repo-
        // wide grep of every user-creation path confirms tenant_id is never
        // populated), so every admin landed in the same shared 'default'
        // module-config bucket regardless of real company. Fixed to the
        // real tenant boundary, company_id (cast to string —
        // ModuleManagerService keys on a string tenant id).
        $tenantId = (string) ($request->user()->company_id ?? 0);
        $userId   = $request->user()->id;

        try {
            if ($validated['action'] === 'activate') {
                $this->moduleManager->bulkActivate($validated['modules'], $tenantId, $userId);
            } else {
                foreach ($validated['modules'] as $moduleName) {
                    $this->moduleManager->deactivate($moduleName, $tenantId, $userId);
                }
            }
        } catch (ModuleDeactivationBlockedException $e) {
            return response()->json([
                'success'    => false,
                'message'    => $e->getMessage(),
                'dependents' => $e->getDependents(),
            ], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'action'  => $validated['action'],
                'modules' => $validated['modules'],
            ],
        ]);
    }
}
