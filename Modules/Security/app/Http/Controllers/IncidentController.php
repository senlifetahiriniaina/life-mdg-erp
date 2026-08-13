<?php

namespace Modules\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Security\Models\SecurityIncident;
use Modules\Security\Models\ThreatIndicator;
use Modules\Security\Models\IncidentResponse;

class IncidentController extends Controller
{
    public function indexIncidents(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SecurityIncident::class);

        $status = $request->input('status');

        $incidents = SecurityIncident::where('company_id', auth()->user()->company_id)
            ->when($status, fn($q) => $q->where('incident_status', $status))
            ->latest('detected_at')
            ->paginate($request->input('per_page', 15));

        return response()->json($incidents);
    }

    public function storeIncident(Request $request): JsonResponse
    {
        $this->authorize('create', SecurityIncident::class);

        $validated = $request->validate([
            'incident_type' => 'required|in:intrusion_attempt,data_breach,policy_violation,anomaly',
            'severity' => 'required|in:low,medium,high,critical',
            'description' => 'required|string',
            'threat_indicators' => 'nullable|array',
            'affected_resources' => 'nullable|array',
        ]);

        $incident = SecurityIncident::create([
            'company_id' => auth()->user()->company_id,
            'incident_status' => 'open',
            'detected_at' => now(),
            ...$validated,
        ]);

        return response()->json($incident, 201);
    }

    public function showIncident(SecurityIncident $incident): JsonResponse
    {
        $this->authorize('view', $incident);

        $incident->load('responses');

        return response()->json($incident);
    }

    public function updateIncident(Request $request, SecurityIncident $incident): JsonResponse
    {
        $this->authorize('update', $incident);

        $validated = $request->validate([
            'description' => 'sometimes|string',
            'threat_indicators' => 'nullable|array',
            'affected_resources' => 'nullable|array',
        ]);

        $incident->update($validated);

        return response()->json($incident);
    }

    public function investigateIncident(SecurityIncident $incident): JsonResponse
    {
        $this->authorize('investigate', $incident);

        $incident->update([
            'incident_status' => 'investigating',
            'investigation_started_at' => now(),
        ]);

        return response()->json($incident);
    }

    public function resolveIncident(Request $request, SecurityIncident $incident): JsonResponse
    {
        $this->authorize('resolve', $incident);

        $validated = $request->validate([
            'resolution_notes' => 'required|string',
        ]);

        $incident->update([
            'incident_status' => 'resolved',
            'resolved_at' => now(),
            'resolution_notes' => $validated['resolution_notes'],
        ]);

        return response()->json($incident);
    }

    public function deleteIncident(SecurityIncident $incident): JsonResponse
    {
        $this->authorize('delete', $incident);

        $incident->delete();

        return response()->json(null, 204);
    }

    public function indexThreats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ThreatIndicator::class);

        $level = $request->input('threat_level');

        $threats = ThreatIndicator::when($level, fn($q) => $q->where('threat_level', $level))
            ->where('is_whitelisted', false)
            ->paginate($request->input('per_page', 25));

        return response()->json($threats);
    }

    public function storeThreat(Request $request): JsonResponse
    {
        $this->authorize('create', ThreatIndicator::class);

        $validated = $request->validate([
            'indicator_type' => 'required|in:ip_address,domain,hash,email,user_agent',
            'indicator_value' => 'required|string|unique:threat_indicators',
            'threat_level' => 'required|in:low,medium,high,critical',
            'description' => 'required|string',
            'source' => 'in:internal_detection,threat_feed,user_report',
        ]);

        $threat = ThreatIndicator::create([
            'detected_at' => now(),
            ...$validated,
        ]);

        return response()->json($threat, 201);
    }

    public function whitelistThreat(ThreatIndicator $threat): JsonResponse
    {
        $this->authorize('whitelist', $threat);

        $threat->update(['is_whitelisted' => true]);

        return response()->json($threat);
    }

    public function unwhitelistThreat(ThreatIndicator $threat): JsonResponse
    {
        $this->authorize('unwhitelist', $threat);

        $threat->update(['is_whitelisted' => false]);

        return response()->json($threat);
    }

    public function indexResponses(SecurityIncident $incident, Request $request): JsonResponse
    {
        $this->authorize('view', $incident);

        $responses = $incident->responses()
            ->paginate($request->input('per_page', 25));

        return response()->json($responses);
    }

    public function storeResponse(Request $request, SecurityIncident $incident): JsonResponse
    {
        $this->authorize('update', $incident);

        $validated = $request->validate([
            'response_type' => 'required|in:alert,block,quarantine,investigate,isolate',
            'response_config' => 'nullable|array',
        ]);

        $response = $incident->responses()->create([
            'response_status' => 'pending',
            ...$validated,
        ]);

        return response()->json($response, 201);
    }
}
