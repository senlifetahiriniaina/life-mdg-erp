<?php

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Campaign;
use Modules\CRM\Models\CampaignEnrollment;

class CampaignController extends Controller
{
    /**
     * Chantier "CRM tenant-isolation follow-up": crm_campaigns had zero company/tenant column
     * of any kind, and this index() listed every company's campaigns regardless of caller —
     * found while investigating the (now-deleted) CampaignOrchestrationService, a fully dead
     * duplicate that had been written against a schema that never matched this controller's
     * real one. Now scoped by the new, additive `company_id` column.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Campaign::class);

        $campaigns = Campaign::query()
            ->where('company_id', $request->user()->company_id)
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->with('owner')
            ->paginate(15);

        return response()->json($campaigns);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'type'       => 'required|in:email,sms,whatsapp,multi_channel',
            'channels'   => 'nullable|array',
            'segments'   => 'nullable|array',
        ]);

        $campaign = Campaign::create(array_merge($validated, [
            'owner_id' => auth()->id(),
            'company_id' => $request->user()->company_id,
            'status'   => 'draft',
        ]));

        return response()->json($campaign, 201);
    }

    public function show(Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        return response()->json($campaign->load(['stages', 'enrollments', 'analytics']));
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('update', $campaign);

        $validated = $request->validate([
            'name'        => 'string|max:255',
            'description' => 'nullable|string',
            'status'      => 'in:draft,active,paused,completed',
            'segments'    => 'nullable|array',
        ]);

        $campaign->update($validated);

        return response()->json($campaign);
    }

    public function launch(Campaign $campaign): JsonResponse
    {
        $this->authorize('launch', $campaign);

        $campaign->update(['status' => 'active', 'start_date' => now()]);

        return response()->json(['message' => 'Campaign launched', 'campaign' => $campaign]);
    }

    public function pause(Campaign $campaign): JsonResponse
    {
        $this->authorize('pause', $campaign);

        $campaign->update(['status' => 'paused']);

        return response()->json(['message' => 'Campaign paused']);
    }

    public function getAnalytics(Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $analytics = $campaign->analytics()
            ->orderBy('date', 'desc')
            ->get();

        $enrollments = $campaign->enrollments()->count();
        $conversions = $campaign->enrollments()->where('status', 'completed')->count();

        return response()->json([
            'summary' => [
                'total_enrolled'    => $enrollments,
                'total_converted'   => $conversions,
                'conversion_rate'   => $enrollments > 0 ? ($conversions / $enrollments) * 100 : 0,
                'total_interactions' => $campaign->enrollments()->sum('interactions'),
            ],
            'daily_analytics' => $analytics,
        ]);
    }
}
