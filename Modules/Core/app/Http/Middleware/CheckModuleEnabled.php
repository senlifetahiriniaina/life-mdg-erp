<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Services\ModuleManager;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleEnabled
{
    public function __construct(private readonly ModuleManager $moduleManager) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $userId = $request->user()?->id;

        if (! $userId || ! $this->moduleManager->isEnabled($module, null)) {
            return response()->json([
                'message' => "Module {$module} is not enabled.",
            ], 403);
        }

        return $next($request);
    }
}
