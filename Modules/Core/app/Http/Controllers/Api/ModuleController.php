<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Services\ModuleManager;

/**
 * @group Core - Module
 *
 * Enable and disable ERP modules per tenant.
 */
class ModuleController extends Controller
{
    public function __construct(private readonly ModuleManager $moduleManager) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->id;
        $modules = $this->moduleManager->enabledModules((string) $tenantId);

        return response()->json(['modules' => $modules]);
    }

    public function enable(Request $request, string $module): JsonResponse
    {
        $request->validate([
            'department' => ['nullable', 'string'],
        ]);

        $this->moduleManager->enable(
            (string) $request->user()->id,
            $module,
            $request->input('department')
        );

        return response()->json(['message' => "Module {$module} enabled."]);
    }

    public function disable(Request $request, string $module): JsonResponse
    {
        $request->validate([
            'department' => ['nullable', 'string'],
        ]);

        $this->moduleManager->disable(
            (string) $request->user()->id,
            $module,
            $request->input('department')
        );

        return response()->json(['message' => "Module {$module} disabled."]);
    }

    public function updateSettings(Request $request, string $module): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        return response()->json(['message' => "Settings updated for module {$module}.", 'settings' => $validated['settings']]);
    }
}
