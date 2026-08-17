<?php

declare(strict_types=1);

namespace Modules\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
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
     * @queryParam service_type string Filter by service type. Example: internal-api
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceIdentity::class);

        $identities = ServiceIdentity::where('company_id', auth()->user()->company_id)
            ->when($request->filled('service_type'), fn ($q) => $q->where('service_type', $request->service_type))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($identities);
    }

    /**
     * Get a single service identity.
     */
    public function show(ServiceIdentity $serviceIdentity): JsonResponse
    {
        $this->authorize('view', $serviceIdentity);

        return response()->json(['data' => $serviceIdentity]);
    }

    /**
     * Create a service identity. The private key is only ever returned once,
     * at creation time — only its hash is persisted.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ServiceIdentity::class);

        $validated = $request->validate([
            'service_name'           => 'required|string|max:128',
            'service_type'           => 'required|string|max:32',
            'allowed_permissions'    => 'sometimes|array',
            'resource_restrictions'  => 'sometimes|array',
            'expires_at'             => 'nullable|date',
        ]);

        $privateKey = Str::random(64);

        $identity = ServiceIdentity::create([
            'company_id'        => auth()->user()->company_id,
            'public_key'        => Str::random(32),
            'private_key_hash'  => Hash::make($privateKey),
            'last_rotated_at'   => now(),
            'is_active'         => true,
            ...$validated,
        ]);

        return response()->json([
            'data'    => $identity,
            'private_key' => $privateKey,
            'message' => 'Service identity created — save the private key, it will not be shown again',
        ], 201);
    }

    /**
     * Update a service identity.
     */
    public function update(Request $request, ServiceIdentity $serviceIdentity): JsonResponse
    {
        $this->authorize('update', $serviceIdentity);

        $validated = $request->validate([
            'service_name'           => 'sometimes|string|max:128',
            'allowed_permissions'    => 'sometimes|array',
            'resource_restrictions'  => 'sometimes|array',
            'expires_at'             => 'nullable|date',
        ]);

        $serviceIdentity->update($validated);

        return response()->json(['data' => $serviceIdentity, 'message' => 'Service identity updated']);
    }

    /**
     * Delete a service identity.
     */
    public function destroy(ServiceIdentity $serviceIdentity): JsonResponse
    {
        $this->authorize('delete', $serviceIdentity);

        $serviceIdentity->delete();

        return response()->json(['message' => 'Service identity deleted']);
    }

    /**
     * Rotate credentials for a service identity.
     */
    public function rotateCredentials(ServiceIdentity $serviceIdentity): JsonResponse
    {
        $this->authorize('rotate', $serviceIdentity);

        $privateKey = Str::random(64);

        $serviceIdentity->update([
            'public_key'       => Str::random(32),
            'private_key_hash' => Hash::make($privateKey),
            'last_rotated_at'  => now(),
        ]);

        return response()->json([
            'data'    => ['public_key' => $serviceIdentity->public_key, 'private_key' => $privateKey],
            'message' => 'Credentials rotated — save the new private key, it will not be shown again',
        ]);
    }

    /**
     * Revoke a service identity.
     */
    public function revoke(ServiceIdentity $serviceIdentity): JsonResponse
    {
        $this->authorize('update', $serviceIdentity);

        $serviceIdentity->update(['is_active' => false]);

        return response()->json(['message' => 'Service identity revoked']);
    }
}
