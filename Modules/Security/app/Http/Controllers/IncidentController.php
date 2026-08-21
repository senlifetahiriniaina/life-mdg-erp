<?php

namespace Modules\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Security\Models\SecurityIncident;
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

    // Chantier 32.3: indexThreats/storeThreat/whitelistThreat/unwhitelistThreat
    // used to live here — deleted as a confirmed-dead duplicate of
    // ThreatIndicatorController's own index()/store() (zero real caller
    // anywhere outside their own test file, and a different indicator_type
    // enum than the controller everything real actually calls). The
    // whitelist/unwhitelist capability was ported onto ThreatIndicatorController
    // instead of being lost — see its own docblock.

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
