<?php

namespace Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Analytics\Models\ABTestRun;
use Modules\Analytics\Models\MLModel;
use Modules\Analytics\Models\MLModelVersion;

class MLModelController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(MLModel::class, 'ml_model');
    }

    public function index(Request $request): JsonResponse
    {
        $query = MLModel::where('company_id', auth()->user()->company_id)
            ->with(['versions', 'createdBy', 'deployedBy']);

        if ($request->filled('category')) {
            $query->where('model_category', $request->input('category'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('framework')) {
            $query->where('framework', $request->input('framework'));
        }

        $models = $query->paginate($request->input('per_page', 15));

        return response()->json($models);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model_key' => 'required|string|unique:ml_models,model_key',
            'model_name' => 'required|string|max:255',
            'model_category' => 'required|in:prediction,recommendation,anomaly',
            'framework' => 'required|in:sklearn,xgboost,lightgbm,prophet,custom',
            'description' => 'nullable|string',
            'hyperparameters' => 'nullable|array',
        ]);

        $model = MLModel::create([
            ...$validated,
            'company_id' => auth()->user()->company_id,
            'created_by' => auth()->id(),
            'status' => 'development',
        ]);

        return response()->json($model, 201);
    }

    public function show(MLModel $mlModel): JsonResponse
    {
        return response()->json(
            $mlModel->load(['versions', 'abTests', 'createdBy', 'deployedBy'])
        );
    }

    public function update(Request $request, MLModel $mlModel): JsonResponse
    {
        $validated = $request->validate([
            'model_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'hyperparameters' => 'nullable|array',
        ]);

        $mlModel->update($validated);

        return response()->json($mlModel);
    }

    public function versions(Request $request, MLModel $mlModel): JsonResponse
    {
        $this->authorize('view', $mlModel);

        $versions = $mlModel->versions()
            ->with(['metrics', 'createdBy'])
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json($versions);
    }

    public function deploy(Request $request, MLModel $mlModel): JsonResponse
    {
        $this->authorize('deploy', $mlModel);

        $validated = $request->validate([
            'version_id' => 'required|exists:ml_model_versions,id',
        ]);

        $version = MLModelVersion::findOrFail($validated['version_id']);

        if ($version->ml_model_id !== $mlModel->id) {
            return response()->json(['message' => 'Version does not belong to this model'], 422);
        }

        $mlModel->update([
            'status' => 'production',
            'production_version' => $version->version_number,
            'production_accuracy' => $version->validation_accuracy,
            'deployed_at' => now(),
            'deployed_by' => auth()->id(),
        ]);

        $version->update(['status' => 'active']);

        return response()->json([
            'message' => 'Model deployed successfully',
            'model' => $mlModel,
        ]);
    }

    public function rollback(Request $request, MLModel $mlModel): JsonResponse
    {
        $this->authorize('rollback', $mlModel);

        $validated = $request->validate([
            'version_id' => 'required|exists:ml_model_versions,id',
        ]);

        $version = MLModelVersion::findOrFail($validated['version_id']);

        // Chantier 32.25 (audit 14 couches, Analytics — couche 6, IDOR) :
        // confirmé empiriquement que rollback() acceptait n'importe quel
        // version_id existant, y compris une version appartenant au modèle
        // ML d'une autre société — contrairement à deploy() juste
        // au-dessus, qui vérifie déjà cette appartenance. $mlModel->
        // production_version/production_accuracy pouvaient donc être
        // écrasés avec des valeurs d'un enregistrement d'une société tierce.
        if ($version->ml_model_id !== $mlModel->id) {
            return response()->json(['message' => 'Version does not belong to this model'], 422);
        }

        $mlModel->update([
            'production_version' => $version->version_number,
            'production_accuracy' => $version->validation_accuracy,
        ]);

        return response()->json([
            'message' => 'Rollback successful',
            'model' => $mlModel,
        ]);
    }

    public function abTests(Request $request, MLModel $mlModel): JsonResponse
    {
        $this->authorize('view', $mlModel);

        $query = $mlModel->abTests()
            ->with(['controlVersion', 'variantVersion', 'createdBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $tests = $query->paginate($request->input('per_page', 15));

        return response()->json($tests);
    }

    public function destroy(MLModel $mlModel): JsonResponse
    {
        $mlModel->delete();

        return response()->json(null, 204);
    }
}
