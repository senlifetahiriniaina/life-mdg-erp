<?php

namespace Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Analytics\Models\PredictionModel;
use Modules\Analytics\Models\PredictionResult;

class PredictionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(PredictionModel::class);
    }

    public function index(Request $request): JsonResponse
    {
        $query = PredictionModel::where('company_id', auth()->user()->company_id)
            ->with(['inputs', 'results', 'createdBy']);

        if ($request->filled('type')) {
            $query->where('model_type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $query->where('model_name', 'like', "%{$request->input('search')}%");
        }

        $models = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $models->items(),
            'meta' => [
                'total' => $models->total(),
                'per_page' => $models->perPage(),
                'current_page' => $models->currentPage(),
                'last_page' => $models->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model_name' => 'required|string|max:255',
            'model_type' => 'required|in:churn,revenue,demand,attrition',
            'description' => 'nullable|string',
            'configuration' => 'nullable|array',
        ]);

        $model = PredictionModel::create([
            ...$validated,
            'company_id' => auth()->user()->company_id,
            'created_by' => auth()->id(),
            'status' => 'draft',
        ]);

        return response()->json($model, 201);
    }

    public function show(PredictionModel $predictionModel): JsonResponse
    {
        return response()->json(
            $predictionModel->load(['inputs', 'results', 'metrics', 'createdBy'])
        );
    }

    public function update(Request $request, PredictionModel $predictionModel): JsonResponse
    {
        $validated = $request->validate([
            'model_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'configuration' => 'nullable|array',
        ]);

        $predictionModel->update($validated);

        return response()->json($predictionModel);
    }

    public function train(Request $request, PredictionModel $predictionModel): JsonResponse
    {
        $validated = $request->validate([
            'training_samples' => 'required|integer|min:100',
            'validation_split' => 'required|numeric|between:0.1,0.5',
        ]);

        $predictionModel->update([
            'status' => 'training',
        ]);

        return response()->json([
            'message' => 'Training initiated',
            'model_id' => $predictionModel->id,
            'status' => 'training',
        ]);
    }

    public function results(Request $request, PredictionModel $predictionModel): JsonResponse
    {
        $query = $predictionModel->results()
            ->with(['predictable']);

        if ($request->filled('score_min')) {
            $query->where('prediction_score', '>=', $request->input('score_min'));
        }

        if ($request->filled('class')) {
            $query->where('prediction_class', $request->input('class'));
        }

        $results = $query->paginate($request->input('per_page', 15));

        return response()->json($results);
    }

    public function destroy(PredictionModel $predictionModel): JsonResponse
    {
        $predictionModel->delete();

        return response()->json(null, 204);
    }
}
