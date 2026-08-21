<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\FinanceReview;
use Modules\Accounting\Services\FinanceReviewService;

/**
 * Chantier 26 (volet D) — revue finance mensuelle/trimestrielle : réalisation
 * des objectifs commerciaux validés et du budget, calculées en direct
 * (jamais stockées), plus le journal des revues déjà réalisées par l'équipe
 * finance (commentaires, cadence).
 */
class FinanceReviewController extends Controller
{
    public function __construct(private readonly FinanceReviewService $service) {}

    /** GET /finance-reviews */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinanceReview::class);

        $reviews = FinanceReview::forCompany((int) ($request->user()->company_id ?? 0))
            ->with(['reviewer', 'budget'])
            ->orderByDesc('review_date')
            ->paginate(20);

        return response()->json($reviews);
    }

    /**
     * GET /finance-reviews/realization — calcule en direct la réalisation
     * des objectifs commerciaux validés et, si un budget est fourni, du
     * budget, sur la période demandée. N'enregistre rien.
     */
    public function realization(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinanceReview::class);

        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
            'budget_id'    => 'nullable|integer|exists:acc_budgets,id',
        ]);

        $companyId   = (int) ($request->user()->company_id ?? 0);
        $periodStart = Carbon::parse($validated['period_start']);
        $periodEnd   = Carbon::parse($validated['period_end']);

        $objectives = $this->service->objectiveRealization($companyId, $periodStart, $periodEnd);

        $budgetLines = [];
        if (! empty($validated['budget_id'])) {
            $budget = Budget::where('company_id', $companyId)->findOrFail($validated['budget_id']);
            $budgetLines = $this->service->budgetRealization($budget, $periodStart, $periodEnd);
        }

        return response()->json([
            'period_start'         => $periodStart->toDateString(),
            'period_end'           => $periodEnd->toDateString(),
            'objectives'           => $objectives,
            'budget_lines'         => $budgetLines,
        ]);
    }

    /** POST /finance-reviews */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', FinanceReview::class);

        $validated = $request->validate([
            'cadence'      => 'required|string|in:'.implode(',', FinanceReview::CADENCES),
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
            'review_date'  => 'nullable|date',
            'budget_id'    => 'nullable|integer|exists:acc_budgets,id',
            'comments'     => 'nullable|string|max:5000',
        ]);

        $review = FinanceReview::create([
            'company_id'   => $request->user()->company_id,
            'reviewer_id'  => $request->user()->id,
            'budget_id'    => $validated['budget_id'] ?? null,
            'cadence'      => $validated['cadence'],
            'period_start' => $validated['period_start'],
            'period_end'   => $validated['period_end'],
            'review_date'  => $validated['review_date'] ?? now()->toDateString(),
            'comments'     => $validated['comments'] ?? null,
        ]);

        return response()->json(['data' => $review->load(['reviewer', 'budget'])], 201);
    }

    /** PUT /finance-reviews/{financeReview} */
    public function update(Request $request, FinanceReview $financeReview): JsonResponse
    {
        $this->authorize('update', $financeReview);

        $validated = $request->validate([
            'comments' => 'nullable|string|max:5000',
        ]);

        $financeReview->update($validated);

        return response()->json(['data' => $financeReview->fresh(['reviewer', 'budget'])]);
    }

    /** DELETE /finance-reviews/{financeReview} */
    public function destroy(FinanceReview $financeReview): JsonResponse
    {
        $this->authorize('delete', $financeReview);

        $financeReview->delete();

        return response()->json(null, 204);
    }
}
