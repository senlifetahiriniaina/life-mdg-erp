<?php

namespace Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Analytics\Models\Recommendation;
use Modules\Analytics\Models\RecommendationModel;

class RecommendationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Recommendation::where('company_id', auth()->user()->company_id)
            ->with(['recommendationModel', 'recipient', 'recommended']);

        if ($request->filled('type')) {
            $query->where('recommendation_type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $recommendations = $query->paginate($request->input('per_page', 15));

        return response()->json($recommendations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recommendation_model_id' => 'required|exists:recommendation_models,id',
            'recipient_type' => 'required|string',
            'recipient_id' => 'required|integer',
            'recommended_type' => 'required|string',
            'recommended_id' => 'required|integer',
            'relevance_score' => 'required|numeric|between:0,1',
            'reason' => 'nullable|string|max:255',
        ]);

        $recommendation = Recommendation::create([
            ...$validated,
            'company_id' => auth()->user()->company_id,
            'rank' => 1,
            'status' => 'pending',
        ]);

        return response()->json($recommendation, 201);
    }

    public function show(Recommendation $recommendation): JsonResponse
    {
        $this->authorize('view', $recommendation);

        return response()->json(
            $recommendation->load(['recommendationModel', 'recipient', 'recommended'])
        );
    }

    public function forUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_type' => 'required|string',
            'user_id' => 'required|integer',
            'type' => 'nullable|string',
            'limit' => 'nullable|integer|max:50',
        ]);

        $query = Recommendation::where('company_id', auth()->user()->company_id)
            ->where('recipient_type', $validated['user_type'])
            ->where('recipient_id', $validated['user_id'])
            ->where('status', 'pending')
            ->orderByDesc('relevance_score');

        if ($request->filled('type')) {
            $query->where('recommendation_type', $validated['type']);
        }

        $recommendations = $query->limit($validated['limit'] ?? 10)->get();

        return response()->json($recommendations);
    }

    public function act(Request $request, Recommendation $recommendation): JsonResponse
    {
        $this->authorize('act', $recommendation);

        $recommendation->update([
            'status' => 'acted',
            'acted_at' => now(),
        ]);

        return response()->json($recommendation);
    }

    public function dismiss(Request $request, Recommendation $recommendation): JsonResponse
    {
        $this->authorize('dismiss', $recommendation);

        $recommendation->update([
            'status' => 'dismissed',
        ]);

        return response()->json($recommendation);
    }
}
