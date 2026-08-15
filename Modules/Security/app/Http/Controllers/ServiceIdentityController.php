<?php

declare(strict_types=1);

namespace Modules\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Modules\Security\Models\ServiceIdentity;

/**
 * @group Security - Service Identities
 *
 * Manage service-to-service identities and rotate credentials.
 */
class ServiceIdentityController extends Controller
{
    /**
     * List service identities.
     *
     * @queryParam status string Filter by status (active|revoked). Example: active
     */
    public function index(Request $request): JsonResponse
    {
        $identities = ServiceIdentity::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($identities);
    }

    /**
     * Get a single service identity.
     */
    public function show(ServiceIdentity $serviceIdentity): JsonResponse
    {
        return response()->json(['data' => $serviceIdentity]);
    }

    /**
     * Create a service identity.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'service'     => 'required|string|max:100',
            'description' => 'nullable|string',
            'permissions' => 'sometimes|array',
            'expires_at'  => 'nullable|date',
        ]);

        $identity = ServiceIdentity::create(array_merge($validated, [
            'client_id'     => 'svc-' . Str::random(16),
            'client_secret' => Str::random(64),
            'status'        => 'active',
        ]));

        return response()->json(['data' => $identity, 'message' => 'Service identity created'], 201);
    }

    /**
     * Update a service identity.
     */
    public function update(Request $request, ServiceIdentity $serviceIdentity): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'sometimes|array',
            'expires_at'  => 'nullable|date',
        ]);

        $serviceIdentity->update($validated);

        return response()->json(['data' => $serviceIdentity, 'message' => 'Service identity updated']);
    }

    /**
     * Delete a service identity.
     */
    public function destroy(ServiceIdentity $serviceIdentity): JsonResponse
    {
        $serviceIdentity->delete();

        return response()->json(['message' => 'Service identity deleted']);
    }

    /**
     * Rotate credentials for a service identity.
     */
    public function rotateCredentials(ServiceIdentity $serviceIdentity): JsonResponse
    {
        $newSecret = Str::random(64);

        $serviceIdentity->update([
            'client_secret'    => $newSecret,
            'credentials_rotated_at' => now(),
        ]);

        return response()->json([
            'data'    => ['client_id' => $serviceIdentity->client_id, 'client_secret' => $newSecret],
            'message' => 'Credentials rotated — save the new secret, it will not be shown again',
        ]);
    }

    /**
     * Revoke a service identity.
     */
    public function revoke(ServiceIdentity $serviceIdentity): JsonResponse
    {
        $serviceIdentity->update(['status' => 'revoked', 'revoked_at' => now()]);

        return response()->json(['message' => 'Service identity revoked']);
    }
}
