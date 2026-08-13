<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\BI\Models\Widget;
use Modules\BI\Services\DrillDownService;

/**
 * @group BI - Drill-down
 *
 * Interactive drill-down for widget data.
 */
class DrillDownController extends Controller
{
    public function __construct(private readonly DrillDownService $service) {}

    /**
     * Get drill-down data for a widget by dimension/value.
     */
    public function drill(Request $request, Widget $widget): JsonResponse
    {
        $validated = $request->validate([
            'dimension' => ['nullable', 'string', 'max:100'],
            'value' => ['nullable'],
            'additional_filters' => ['nullable', 'array'],
        ]);

        $result = $this->service->getDrillDownData($widget, $validated);

        return response()->json([
            'widget' => ['id' => $widget->id, 'title' => $widget->title, 'type' => $widget->type],
            'dimensions' => $this->service->getAvailableDimensions($widget),
            'result' => $result,
        ]);
    }
}
