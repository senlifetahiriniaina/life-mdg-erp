<?php

namespace Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Analytics\Models\RecommendationModel;

/**
 * Chantier 32.25 (audit 14 couches, Analytics — couche 9, fake/dead) :
 * `RecommendationModel`/`RecommendationModelPolicy` (5 abilities réelles,
 * bien écrites) n'avaient strictement **aucun** contrôleur ni route —
 * confirmé via grep exhaustif sur `Modules/Analytics/app/Http`. Pourtant
 * `RecommendationController::store()` exige un `recommendation_model_id`
 * réel (`exists:recommendation_models,id`) pour créer la moindre
 * recommandation — sans ce contrôleur, aucun chemin d'API réel ne pouvait
 * jamais faire exister le modèle parent dont toute recommandation dépend
 * (seuls les seeders/factories de test en créaient). Classé « à activer »
 * (pas « mort confirmé » : le modèle est réellement consommé en aval, sa
 * policy est réelle et correcte, aucun doublon ne le remplace) — ce
 * contrôleur reproduit le patron déjà établi par `MLModelController` pour
 * ce même module.
 */
class RecommendationModelController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(RecommendationModel::class, 'recommendation_model');
    }

    public function index(Request $request): JsonResponse
    {
        $query = RecommendationModel::where('company_id', auth()->user()->company_id)
            ->with(['createdBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('recommendation_type', $request->input('type'));
        }

        $models = $query->paginate($request->input('per_page', 15));

        return response()->json($models);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model_name' => 'required|string|max:128',
            'recommendation_type' => 'nullable|string|max:64',
            'algorithm' => 'nullable|string|max:64',
            'description' => 'nullable|string',
            'configuration' => 'nullable|array',
        ]);

        $model = RecommendationModel::create([
            ...$validated,
            'company_id' => auth()->user()->company_id,
            'created_by' => auth()->id(),
            'status' => 'training',
        ]);

        return response()->json($model, 201);
    }

    public function show(RecommendationModel $recommendationModel): JsonResponse
    {
        return response()->json(
            $recommendationModel->load(['recommendations', 'createdBy'])
        );
    }

    public function update(Request $request, RecommendationModel $recommendationModel): JsonResponse
    {
        $validated = $request->validate([
            'model_name' => 'sometimes|string|max:128',
            'description' => 'nullable|string',
            'configuration' => 'nullable|array',
        ]);

        $recommendationModel->update($validated);

        return response()->json($recommendationModel);
    }

    public function train(RecommendationModel $recommendationModel): JsonResponse
    {
        $this->authorize('train', $recommendationModel);

        // Pas de vrai pipeline d'entraînement ML dans ce périmètre — même
        // repli honnête que PredictionController::train() (marque le
        // modèle comme entraîné/actif plutôt que de simuler un score) ;
        // recommendation_count/ctr restent alimentés par l'usage réel
        // (Recommendation::act()/dismiss()), jamais inventés ici.
        $recommendationModel->update([
            'status' => 'active',
            'last_trained_at' => now(),
        ]);

        return response()->json([
            'message' => 'Modèle entraîné avec succès.',
            'model' => $recommendationModel->fresh(),
        ]);
    }

    public function destroy(RecommendationModel $recommendationModel): JsonResponse
    {
        $recommendationModel->delete();

        return response()->json(null, 204);
    }
}
