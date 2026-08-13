<?php

namespace Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Analytics\Models\AnomalyDetectionModel;
use Modules\Analytics\Models\DetectedAnomaly;

class AnomalyDetectionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(AnomalyDetectionModel::class, 'anomaly_detection_model');
    }

    public function index(Request $request): JsonResponse
    {
        $query = AnomalyDetectionModel::where('company_id', auth()->user()->company_id)
            ->with(['anomalies', 'createdBy']);

        if ($request->filled('type')) {
            $query->where('anomaly_type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $models = $query->paginate($request->input('per_page', 15));

        return response()->json($models);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model_name' => 'required|string|max:255',
            'anomaly_type' => 'required|in:transaction,performance,behavioral,system',
            'algorithm' => 'required|in:isolation_forest,local_outlier_factor,mahalanobis',
            'description' => 'nullable|string',
            'anomaly_threshold' => 'required|numeric|between:0,1',
            'configuration' => 'nullable|array',
        ]);

        $model = AnomalyDetectionModel::create([
            ...$validated,
            'company_id' => auth()->user()->company_id,
            'created_by' => auth()->id(),
            'status' => 'active',
        ]);

        return response()->json($model, 201);
    }

    public function show(AnomalyDetectionModel $anomalyDetectionModel): JsonResponse
    {
        return response()->json(
            $anomalyDetectionModel->load(['anomalies', 'createdBy'])
        );
    }

    public function update(Request $request, AnomalyDetectionModel $anomalyDetectionModel): JsonResponse
    {
        $validated = $request->validate([
            'model_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'anomaly_threshold' => 'sometimes|numeric|between:0,1',
            'configuration' => 'nullable|array',
        ]);

        $anomalyDetectionModel->update($validated);

        return response()->json($anomalyDetectionModel);
    }

    public function anomalies(Request $request, AnomalyDetectionModel $anomalyDetectionModel): JsonResponse
    {
        $query = $anomalyDetectionModel->anomalies()
            ->with(['anomalousEntity']);

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $anomalies = $query->paginate($request->input('per_page', 15));

        return response()->json($anomalies);
    }

    public function investigate(Request $request, DetectedAnomaly $anomaly): JsonResponse
    {
        $this->authorize('investigate', $anomaly);

        $anomaly->update([
            'status' => 'investigating',
        ]);

        return response()->json($anomaly);
    }

    public function resolve(Request $request, DetectedAnomaly $anomaly): JsonResponse
    {
        $this->authorize('resolve', $anomaly);

        $validated = $request->validate([
            'resolution_notes' => 'required|string',
        ]);

        $anomaly->update([
            'status' => 'resolved',
            'resolution_notes' => $validated['resolution_notes'],
            'resolved_at' => now(),
        ]);

        return response()->json($anomaly);
    }

    public function dismiss(Request $request, DetectedAnomaly $anomaly): JsonResponse
    {
        $this->authorize('dismiss', $anomaly);

        $anomaly->update([
            'status' => 'dismissed',
        ]);

        return response()->json($anomaly);
    }

    public function destroy(AnomalyDetectionModel $anomalyDetectionModel): JsonResponse
    {
        $anomalyDetectionModel->delete();

        return response()->json(null, 204);
    }
}
