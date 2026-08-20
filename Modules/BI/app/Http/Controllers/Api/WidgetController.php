<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\BI\Models\Dashboard;

/**
 * @group BI - Widgets
 *
 * Chantier 19 Lot 5: `resources/js/Pages/BI/Builder.vue` — the real, routed
 * Dashboard Builder page, the module's core feature — has always POSTed its
 * full widget array to `bi/dashboards/{dashboard}/widgets` on every single
 * "Enregistrer" click (both create and update paths, confirmed via a real
 * grep of the page's own save() function), but that route has never existed
 * anywhere in this module: no controller, no route registration. Every real
 * save has 404'd on the widgets half of the operation — the dashboard
 * record itself would persist (via the already-real `bi/dashboards`
 * routes), but with zero widgets ever attached, confirmed empirically. This
 * is the single most severe finding in this module for Chantier 19 Lot 5.
 */
class WidgetController extends Controller
{
    /**
     * Bulk-replace a dashboard's widgets. The builder always sends its
     * entire current widget list (not incremental diffs), so a
     * delete-and-recreate strategy inside one request matches the
     * frontend's own model exactly — same strategy already used by
     * `PurchaseOrderController::update()`'s line-item sync (Chantier 10).
     */
    public function store(Request $request, Dashboard $dashboard): JsonResponse
    {
        $this->authorize('update', $dashboard);

        $validated = $request->validate([
            'widgets' => ['present', 'array'],
            'widgets.*.type' => ['required', 'string', 'max:100'],
            'widgets.*.title' => ['required', 'string', 'max:255'],
            'widgets.*.description' => ['nullable', 'string'],
            'widgets.*.dataSource' => ['nullable', 'string', 'max:255'],
            'widgets.*.w' => ['nullable', 'integer', 'min:1', 'max:12'],
            'widgets.*.h' => ['nullable', 'integer', 'min:1', 'max:12'],
            'widgets.*.config' => ['nullable', 'array'],
            'widgets.*.refresh_interval' => ['nullable', 'integer', 'min:10'],
        ]);

        $dashboard->widgets()->delete();

        foreach ($validated['widgets'] as $order => $widget) {
            $dashboard->widgets()->create([
                'title' => $widget['title'],
                'type' => $widget['type'],
                'config' => $widget['config'] ?? [],
                // Widget has no dedicated w/h/dataSource/description columns
                // — these live inside the same `position` JSON column
                // Builder.vue's layout otherwise has no use for (this
                // builder is a simple ordered list, not an absolute x/y
                // grid — confirmed via the page's own moveWidget()/no x/y
                // properties anywhere), alongside the render order.
                'position' => [
                    'order' => $order,
                    'w' => $widget['w'] ?? 6,
                    'h' => $widget['h'] ?? 1,
                    'dataSource' => $widget['dataSource'] ?? null,
                    'description' => $widget['description'] ?? null,
                ],
                'refresh_interval' => $widget['refresh_interval'] ?? 300,
            ]);
        }

        return response()->json([
            'data' => $dashboard->widgets()->get(),
        ], 201);
    }
}
