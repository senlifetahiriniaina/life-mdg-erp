<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Helpdesk\Http\Resources\CsatSurveyResource;
use Modules\Helpdesk\Models\CsatCampaign;
use Modules\Helpdesk\Models\CsatSurvey;
use Modules\Helpdesk\Services\CsatReportService;

/**
 * @group Controllers - Csat
 *
 * Manage Csat resources.
 */
class CsatController extends Controller
{
    public function __construct(private readonly CsatReportService $service) {}

    /**
     * GET /api/v1/helpdesk/csat/report
     * Full CSAT report: score, trend, by_agent, distribution, comments
     */
    public function report(Request $request): JsonResponse
    {
        $from = $request->has('from')
            ? Carbon::parse($request->string('from')->toString())
            : Carbon::now()->startOfMonth();

        $to = $request->has('to')
            ? Carbon::parse($request->string('to')->toString())
            : Carbon::now()->endOfMonth();

        return response()->json([
            'score' => $this->service->getCsatScore($from, $to),
            'trend' => $this->service->getWeeklyTrend(),
            'by_agent' => $this->service->getByAgent(),
            'distribution' => $this->service->getScoreDistribution(),
            'comments' => $this->service->getRecentComments(20),
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * GET /api/v1/helpdesk/csat/surveys
     */
    public function indexSurveys(Request $request): JsonResponse
    {
        $surveys = CsatSurvey::with('agent')
            ->when($request->has('ticket_id'), fn ($q) => $q->where('ticket_id', $request->integer('ticket_id')))
            ->when($request->has('campaign_id'), fn ($q) => $q->where('campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('responded'), fn ($q) => $request->boolean('responded')
                ? $q->whereNotNull('responded_at')
                : $q->whereNull('responded_at'))
            ->latest()
            ->paginate(25);

        return response()->json([
            'data' => CsatSurveyResource::collection($surveys->items()),
            'total' => $surveys->total(),
            'per_page' => $surveys->perPage(),
            'current_page' => $surveys->currentPage(),
        ]);
    }

    /**
     * POST /api/v1/helpdesk/csat/surveys
     */
    public function storeSurvey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticket_id' => ['required', 'integer', 'exists:hd_tickets,id'],
            'campaign_id' => ['nullable', 'integer', 'exists:helpdesk_csat_campaigns,id'],
            'agent_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $survey = CsatSurvey::create([
            ...$validated,
            'sent_at' => now(),
        ]);

        return response()->json(new CsatSurveyResource($survey), 201);
    }

    /**
     * PUT /api/v1/helpdesk/csat/surveys/{survey}
     * Record a survey response (score + optional comment)
     */
    public function updateSurvey(Request $request, CsatSurvey $survey): JsonResponse
    {
        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $survey->update([
            ...$validated,
            'responded_at' => $survey->responded_at ?? now(),
        ]);

        return response()->json(new CsatSurveyResource($survey->fresh('agent')));
    }

    /**
     * GET /api/v1/helpdesk/csat/campaigns
     */
    public function indexCampaigns(): JsonResponse
    {
        $campaigns = CsatCampaign::withCount('surveys')->orderByDesc('created_at')->get();

        return response()->json(['data' => $campaigns]);
    }

    /**
     * POST /api/v1/helpdesk/csat/campaigns
     */
    public function storeCampaign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['nullable', 'string', 'max:100'],
            'delay_hours' => ['nullable', 'integer', 'min:0'],
            'question_text' => ['required', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        $campaign = CsatCampaign::create($validated);

        return response()->json($campaign, 201);
    }
}
