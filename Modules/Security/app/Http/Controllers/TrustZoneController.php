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
     * @queryParam level string Filter by trust level (high|medium|low|untrusted). Example: high
     */
    public function index(Request $request): JsonResponse
    {
        $zones = TrustZone::query()
            ->when($request->filled('level'), fn ($q) => $q->where('trust_level', $request->level))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($zones);
    }

    /**
     * Get a single trust zone.
     */
    public function show(TrustZone $trustZone): JsonResponse
    {
        return response()->json(['data' => $trustZone]);
    }

    /**
     * Create a trust zone.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'trust_level' => 'required|in:high,medium,low,untrusted',
            'description' => 'nullable|string',
            'ip_ranges'   => 'sometimes|array',
            'ip_ranges.*' => 'string',
            'policies'    => 'sometimes|array',
        ]);

        $zone = TrustZone::create($validated);

        return response()->json(['data' => $zone, 'message' => 'Trust zone created'], 201);
    }

    /**
     * Update a trust zone.
     */
    public function update(Request $request, TrustZone $trustZone): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'trust_level' => 'sometimes|in:high,medium,low,untrusted',
            'description' => 'nullable|string',
            'ip_ranges'   => 'sometimes|array',
            'ip_ranges.*' => 'string',
            'policies'    => 'sometimes|array',
        ]);

        $trustZone->update($validated);

        return response()->json(['data' => $trustZone, 'message' => 'Trust zone updated']);
    }

    /**
     * Delete a trust zone.
     */
    public function destroy(TrustZone $trustZone): JsonResponse
    {
        $trustZone->delete();

        return response()->json(['message' => 'Trust zone deleted']);
    }

    /**
     * Assign a resource (IP or service) to a trust zone.
     */
    public function assignResource(Request $request, TrustZone $trustZone): JsonResponse
    {
        $validated = $request->validate([
            'resource_type'  => 'required|in:ip,service,user_group',
            'resource_value' => 'required|string|max:255',
        ]);

        $resources = $trustZone->resources ?? [];
        $resources[] = $validated;

        $trustZone->update(['resources' => $resources]);

        return response()->json(['data' => $trustZone, 'message' => 'Resource assigned to trust zone']);
    }
}
