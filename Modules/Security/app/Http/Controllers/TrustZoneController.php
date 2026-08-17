<?php

declare(strict_types=1);

namespace Modules\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Security\Models\TrustZone;

/**
 * @group Security - Trust Zones
 *
 * Manage network trust zones and assign resources.
 */
class TrustZoneController extends Controller
{
    /**
     * List all trust zones.
     *
     * @queryParam type string Filter by zone type. Example: internal
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TrustZone::class);

        $zones = TrustZone::where('company_id', auth()->user()->company_id)
            ->when($request->filled('type'), fn ($q) => $q->where('zone_type', $request->type))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($zones);
    }

    /**
     * Get a single trust zone.
     */
    public function show(TrustZone $trustZone): JsonResponse
    {
        $this->authorize('view', $trustZone);

        return response()->json(['data' => $trustZone]);
    }

    /**
     * Create a trust zone.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', TrustZone::class);

        $validated = $request->validate([
            'zone_name'                => 'required|string|max:128',
            'zone_type'                => 'nullable|string|max:32',
            'description'              => 'nullable|string',
            'cidr_blocks'              => 'sometimes|array',
            'cidr_blocks.*'            => 'string',
            'device_policies'          => 'sometimes|array',
            'authentication_policies'  => 'sometimes|array',
            'trust_score_minimum'      => 'nullable|integer|min:0|max:100',
        ]);

        $zone = TrustZone::create([
            'company_id' => auth()->user()->company_id,
            ...$validated,
        ]);

        return response()->json(['data' => $zone, 'message' => 'Trust zone created'], 201);
    }

    /**
     * Update a trust zone.
     */
    public function update(Request $request, TrustZone $trustZone): JsonResponse
    {
        $this->authorize('update', $trustZone);

        $validated = $request->validate([
            'zone_name'                => 'sometimes|string|max:128',
            'zone_type'                => 'nullable|string|max:32',
            'description'              => 'nullable|string',
            'cidr_blocks'              => 'sometimes|array',
            'cidr_blocks.*'            => 'string',
            'device_policies'          => 'sometimes|array',
            'authentication_policies'  => 'sometimes|array',
            'trust_score_minimum'      => 'nullable|integer|min:0|max:100',
        ]);

        $trustZone->update($validated);

        return response()->json(['data' => $trustZone, 'message' => 'Trust zone updated']);
    }

    /**
     * Delete a trust zone.
     */
    public function destroy(TrustZone $trustZone): JsonResponse
    {
        $this->authorize('delete', $trustZone);

        $trustZone->delete();

        return response()->json(['message' => 'Trust zone deleted']);
    }

    /**
     * Assign a resource (IP or service) to a trust zone.
     */
    public function assignResource(Request $request, TrustZone $trustZone): JsonResponse
    {
        $this->authorize('update', $trustZone);

        $validated = $request->validate([
            'resource_type'  => 'required|in:ip,service,user_group',
            'resource_value' => 'required|string|max:255',
        ]);

        $resources = $trustZone->assigned_resources ?? [];
        $resources[] = $validated;

        $trustZone->update(['assigned_resources' => $resources]);

        return response()->json(['data' => $trustZone, 'message' => 'Resource assigned to trust zone']);
    }
}
