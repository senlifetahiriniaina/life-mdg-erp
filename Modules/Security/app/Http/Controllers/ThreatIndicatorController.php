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
     * @queryParam type string Filter by type (ip|domain|hash|url|email|user_agent). Example: ip
     * @queryParam severity string Filter by severity (low|medium|high|critical). Example: high
     * @queryParam is_whitelisted boolean Show whitelisted only. Example: 0
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ThreatIndicator::class);

        $threats = ThreatIndicator::query()
            ->when($request->filled('type'), fn ($q) => $q->where('indicator_type', $request->type))
            ->when($request->filled('severity'), fn ($q) => $q->where('threat_level', $request->severity))
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
        $this->authorize('view', $threat);

        return response()->json(['data' => $threat]);
    }

    /**
     * Create a new threat indicator.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ThreatIndicator::class);

        $validated = $request->validate([
            'indicator_type' => 'required|in:ip,domain,hash,url,email,user_agent',
            // Chantier 32.3: a real DB-level unique constraint on
            // indicator_value exists (see database/migrations/2026_08_19_000002_
            // add_unique_index_to_security_threat_indicators.php) but this
            // validation never mirrored it — a duplicate indicator_value threw a
            // raw QueryException (500) instead of a clean 422, confirmed
            // empirically before this fix. The now-deleted legacy
            // IncidentController::storeThreat() had this rule, this one never did.
            'indicator_value'=> 'required|string|max:500|unique:security_threat_indicators,indicator_value',
            'severity'       => 'required|in:low,medium,high,critical',
            'description'    => 'nullable|string',
            'source'         => 'nullable|string|max:255',
            'expires_at'     => 'nullable|date',
        ]);

        $threat = ThreatIndicator::create([
            'detected_at' => now(),
            ...collect($validated)->except('severity')->all(),
            'threat_level' => $validated['severity'],
        ]);

        return response()->json(['data' => $threat, 'message' => 'Threat indicator created'], 201);
    }

    /**
     * Update a threat indicator.
     */
    public function update(Request $request, ThreatIndicator $threat): JsonResponse
    {
        $this->authorize('update', $threat);

        $validated = $request->validate([
            'severity'    => 'sometimes|in:low,medium,high,critical',
            'description' => 'nullable|string',
            'source'      => 'nullable|string|max:255',
            'expires_at'  => 'nullable|date',
        ]);

        if (array_key_exists('severity', $validated)) {
            $validated['threat_level'] = $validated['severity'];
            unset($validated['severity']);
        }

        $threat->update($validated);

        return response()->json(['data' => $threat, 'message' => 'Threat indicator updated']);
    }

    /**
     * Whitelist a threat indicator (suppress it from active-threat checks).
     *
     * Chantier 32.3: ported over from the now-deleted legacy
     * IncidentController::whitelistThreat() — the one real, non-duplicate
     * capability that controller had over this one.
     */
    public function whitelist(ThreatIndicator $threat): JsonResponse
    {
        $this->authorize('whitelist', $threat);

        $threat->update(['is_whitelisted' => true]);

        return response()->json(['data' => $threat, 'message' => 'Threat indicator whitelisted']);
    }

    /**
     * Un-whitelist a threat indicator.
     */
    public function unwhitelist(ThreatIndicator $threat): JsonResponse
    {
        $this->authorize('unwhitelist', $threat);

        $threat->update(['is_whitelisted' => false]);

        return response()->json(['data' => $threat, 'message' => 'Threat indicator un-whitelisted']);
    }

    /**
     * Delete a threat indicator.
     */
    public function destroy(ThreatIndicator $threat): JsonResponse
    {
        $this->authorize('delete', $threat);

        $threat->delete();

        return response()->json(['message' => 'Threat indicator deleted']);
    }

    /**
     * Get severity level counts.
     */
    public function severitySummary(): JsonResponse
    {
        $this->authorize('viewAny', ThreatIndicator::class);

        $summary = ThreatIndicator::query()
            ->selectRaw('threat_level, COUNT(*) as count')
            ->groupBy('threat_level')
            ->pluck('count', 'threat_level');

        return response()->json(['data' => $summary]);
    }
}
