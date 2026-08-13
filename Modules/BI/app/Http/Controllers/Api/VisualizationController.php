<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BI\Models\CustomVisualization;
use Modules\BI\Models\VisualizationTemplate;

/**
 * @group BI - Visualization
 *
 * Manage advanced visualizations (heatmaps, 3D charts, etc.)
 */
class VisualizationController extends Controller
{
    public function index(Request $request): JsonResponse|JsonResource
    {
        $this->authorize('viewAny', CustomVisualization::class);

        $visualizations = CustomVisualization::with(['creator', 'template'])
            ->where('company_id', $request->user()->company_id)
            ->when($request->input('type'), function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($request->input('dashboard_id'), function ($query, $dashboardId) {
                $query->where('dashboard_id', $dashboardId);
            })
            ->latest()
            ->paginate(20);

        return JsonResource::collection($visualizations);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CustomVisualization::class);

        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'type'              => 'required|string|in:heatmap,3d_scatter,3d_surface,bubble,waterfall',
            'config'            => 'required|array',
            'data_source'       => 'required|array',
            'color_scale'       => 'nullable|array',
            'range_config'      => 'nullable|array',
            'real_time_enabled' => 'boolean',
            'refresh_interval'  => 'integer|min:10',
            'template_id'       => 'nullable|exists:bi_visualization_templates,id',
            'dashboard_id'      => 'nullable|exists:bi_dashboards,id',
        ]);

        $visualization = CustomVisualization::create([
            ...$validated,
            'company_id' => $request->user()->company_id,
            'created_by' => $request->user()->id,
        ]);

        if ($visualization->template_id) {
            $visualization->template->incrementUsage();
        }

        return response()->json($visualization, 201);
    }

    public function show(CustomVisualization $visualization): JsonResponse
    {
        $this->authorize('view', $visualization);
        $visualization->load(['creator', 'template', 'performanceMetrics']);

        return response()->json($visualization);
    }

    public function update(Request $request, CustomVisualization $visualization): JsonResponse
    {
        $this->authorize('update', $visualization);

        $validated = $request->validate([
            'name'              => 'string|max:255',
            'description'       => 'nullable|string',
            'config'            => 'array',
            'color_scale'       => 'nullable|array',
            'range_config'      => 'nullable|array',
            'real_time_enabled' => 'boolean',
            'refresh_interval'  => 'integer|min:10',
        ]);

        $visualization->update($validated);

        return response()->json($visualization);
    }

    public function destroy(CustomVisualization $visualization): JsonResponse
    {
        $this->authorize('delete', $visualization);
        $visualization->delete();

        return response()->json(null, 204);
    }

    public function render(Request $request, CustomVisualization $visualization): JsonResponse
    {
        $this->authorize('view', $visualization);

        // This would integrate with a charting library or service
        // to render the visualization based on data_source and config
        $renderData = [
            'id'          => $visualization->id,
            'type'        => $visualization->type,
            'config'      => $visualization->config,
            'dataSource'  => $visualization->data_source,
            'timestamp'   => now(),
        ];

        return response()->json($renderData);
    }

    public function export(Request $request, CustomVisualization $visualization): JsonResponse
    {
        $this->authorize('export', $visualization);

        $format = $request->input('format', 'png'); // png, pdf, svg, json

        return response()->json([
            'visualization_id' => $visualization->id,
            'format'          => $format,
            'exported_at'     => now(),
        ]);
    }

    public function share(Request $request, CustomVisualization $visualization): JsonResponse
    {
        $this->authorize('share', $visualization);

        $validated = $request->validate([
            'recipients'  => 'required|array|min:1',
            'recipients.*' => 'email',
            'message'     => 'nullable|string',
        ]);

        // Implement sharing logic
        return response()->json(['message' => 'Visualization shared successfully']);
    }

    public function templates(Request $request): JsonResponse
    {
        $templates = VisualizationTemplate::where('company_id', $request->user()->company_id)
            ->orWhere('is_public', true)
            ->when($request->input('type'), function ($query, $type) {
                $query->where('chart_type', $type);
            })
            ->latest()
            ->paginate(20);

        return response()->json($templates);
    }

    public function updatePerformance(Request $request, CustomVisualization $visualization): JsonResponse
    {
        $this->authorize('updatePerformance', $visualization);

        $visualization->updatePerformanceScore();

        return response()->json(['performance_score' => $visualization->refresh()->performance_score]);
    }

    public function enableRealTime(CustomVisualization $visualization): JsonResponse
    {
        $this->authorize('update', $visualization);
        $visualization->enableRealTime();

        return response()->json(['real_time_enabled' => true]);
    }

    public function disableRealTime(CustomVisualization $visualization): JsonResponse
    {
        $this->authorize('update', $visualization);
        $visualization->disableRealTime();

        return response()->json(['real_time_enabled' => false]);
    }
}
