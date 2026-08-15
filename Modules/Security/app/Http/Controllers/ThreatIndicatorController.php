<?php

declare(strict_types=1);

namespace Modules\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Security\Models\ThreatIndicator;

/**
 * @group Security - Threat Indicators
 *
 * Manage threat indicators (IPs, domains, hashes) for proactive threat detection.
 */
class ThreatIndicatorController extends Controller
{
    /**
     * List threat indicators.
     *
     * @queryParam type string Filter by type (ip|domain|hash|url). Example: ip
     * @queryParam severity string Filter by severity (low|medium|high|critical). Example: high
     * @queryParam is_whitelisted boolean Show whitelisted only. Example: 0
     */
    public function index(Request $request): JsonResponse
    {
        $threats = ThreatIndicator::query()
            ->when($request->filled('type'), fn ($q) => $q->where('indicator_type', $request->type))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->severity))
            ->when($request->has('is_whitelisted'), fn ($q) => $q->where('is_whitelisted', filter_var($request->is_whitelisted, FILTER_VALIDATE_BOOLEAN)))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($threats);
    }

    /**
     * Get a single threat indicator.
     */
    public function show(ThreatIndicator $threat): JsonResponse
    {
        return response()->json(['data' => $threat]);
    }

    /**
     * Create a new threat indicator.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'indicator_type' => 'required|in:ip,domain,hash,url,email',
            'indicator_value'=> 'required|string|max:500',
            'severity'       => 'required|in:low,medium,high,critical',
            'description'    => 'nullable|string',
            'source'         => 'nullable|string|max:255',
            'expires_at'     => 'nullable|date',
        ]);

        $threat = ThreatIndicator::create($validated);

        return response()->json(['data' => $threat, 'message' => 'Threat indicator created'], 201);
    }

    /**
     * Update a threat indicator.
     */
    public function update(Request $request, ThreatIndicator $threat): JsonResponse
    {
        $validated = $request->validate([
            'severity'    => 'sometimes|in:low,medium,high,critical',
            'description' => 'nullable|string',
            'source'      => 'nullable|string|max:255',
            'expires_at'  => 'nullable|date',
        ]);

        $threat->update($validated);

        return response()->json(['data' => $threat, 'message' => 'Threat indicator updated']);
    }

    /**
     * Delete a threat indicator.
     */
    public function destroy(ThreatIndicator $threat): JsonResponse
    {
        $threat->delete();

        return response()->json(['message' => 'Threat indicator deleted']);
    }

    /**
     * Get severity level counts.
     */
    public function severitySummary(): JsonResponse
    {
        $summary = ThreatIndicator::query()
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity');

        return response()->json(['data' => $summary]);
    }
}
