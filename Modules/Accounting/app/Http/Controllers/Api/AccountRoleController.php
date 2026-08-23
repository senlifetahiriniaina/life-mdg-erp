<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Services\AccountRoleService;

/**
 * Chantier 37 — administration des rôles de compte comptable
 * (Modules\Settings, module 'accounting'), suite directe du remap
 * Chantier 36. Aucune Policy dédiée — même convention que
 * ChartOfAccountController/TreasuryImportController/OperationTemplateController
 * dans ce module : le gate de route (role:accountant,finance-manager,manager,admin)
 * suffit, ce module n'utilise pas de classe Policy pour ce type d'endpoint.
 */
class AccountRoleController extends Controller
{
    public function __construct(private readonly AccountRoleService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->service->listRoles()]);
    }

    public function update(Request $request, string $role): JsonResponse
    {
        $validated = $request->validate(['code' => 'required|string']);

        try {
            $this->service->setRole($role, $validated['code']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => collect($this->service->listRoles())->firstWhere('role', $role),
        ]);
    }
}
