<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Services\SecretsService;
use Modules\Core\Services\SecretAccessControl;
use Modules\Core\Services\SecretRotationManager;

/**
 * SecretsController: API endpoints for secrets management
 *
 * Provides REST API for creating, retrieving, rotating, and managing secrets
 * with full authentication and authorization checks.
 */
class SecretsController extends Controller
{
    private SecretsService $secretsService;
    private SecretAccessControl $accessControl;
    private SecretRotationManager $rotationManager;

    public function __construct(
        SecretsService $secretsService,
        SecretAccessControl $accessControl,
        SecretRotationManager $rotationManager
    ) {
        $this->secretsService = $secretsService;
        $this->accessControl = $accessControl;
        $this->rotationManager = $rotationManager;
    }

    /**
     * Store a new secret
     * POST /api/v1/secrets
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'value' => 'required|string',
                'type' => 'required|in:api_key,oauth_token,database_credential,ssh_key,certificate',
                'expires_at' => 'nullable|date_format:Y-m-d H:i:s',
                'tags' => 'nullable|array',
                'rotation_interval' => 'nullable|integer|min:1|max:365',
            ]);

            // Store secret
            $secret = $this->secretsService->storeSecret(
                $validated['name'],
                $validated['value'],
                $validated['type'],
                [
                    'expires_at' => $validated['expires_at'] ?? null,
                    'tags' => $validated['tags'] ?? null,
                    'rotation_interval' => $validated['rotation_interval'] ?? null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Secret created successfully',
                'data' => [
                    'id' => $secret->id,
                    'name' => $secret->name,
                    'type' => $secret->type,
                    'created_at' => $secret->created_at,
                    'expires_at' => $secret->expires_at,
                ],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create secret',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Retrieve a secret value
     * GET /api/v1/secrets/{name}
     *
     * @param Request $request
     * @param string $name Secret name
     * @return JsonResponse
     */
    public function show(Request $request, string $name): JsonResponse
    {
        try {
            $value = $this->secretsService->retrieveSecret($name);

            return response()->json([
                'success' => true,
                'data' => [
                    'name' => $name,
                    'value' => $value,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve secret',
                'error' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Get secret metadata without retrieving value
     * GET /api/v1/secrets/{name}/metadata
     *
     * @param Request $request
     * @param string $name Secret name
     * @return JsonResponse
     */
    public function metadata(Request $request, string $name): JsonResponse
    {
        try {
            $metadata = $this->secretsService->getSecretMetadata($name);

            if (!$metadata) {
                return response()->json([
                    'success' => false,
                    'message' => 'Secret not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $metadata,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve metadata',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Rotate a secret
     * PUT /api/v1/secrets/{name}/rotate
     *
     * @param Request $request
     * @param string $name Secret name
     * @return JsonResponse
     */
    public function rotate(Request $request, string $name): JsonResponse
    {
        try {
            $validated = $request->validate([
                'new_value' => 'nullable|string',
            ]);

            $secret = $this->secretsService->rotateSecret(
                $name,
                $validated['new_value'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Secret rotated successfully',
                'data' => [
                    'id' => $secret->id,
                    'name' => $secret->name,
                    'rotated_at' => $secret->rotated_at,
                    'next_rotation' => $secret->next_rotation,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rotate secret',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Revoke a secret
     * DELETE /api/v1/secrets/{name}
     *
     * @param Request $request
     * @param string $name Secret name
     * @return JsonResponse
     */
    public function destroy(Request $request, string $name): JsonResponse
    {
        try {
            $this->secretsService->revokeSecret($name);

            return response()->json([
                'success' => true,
                'message' => 'Secret revoked successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke secret',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * List accessible secrets
     * GET /api/v1/secrets
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'type' => $request->query('type'),
                'tags' => $request->query('tags'),
                'active_only' => $request->boolean('active_only', true),
                'exclude_expired' => $request->boolean('exclude_expired', true),
            ];

            $secrets = $this->secretsService->listSecrets($filters);

            return response()->json([
                'success' => true,
                'data' => $secrets->map(function ($secret) {
                    return [
                        'id' => $secret->id,
                        'name' => $secret->name,
                        'type' => $secret->type,
                        'is_active' => $secret->is_active,
                        'expires_at' => $secret->expires_at,
                        'days_until_expiration' => $secret->daysUntilExpiration(),
                    ];
                }),
                'count' => $secrets->count(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to list secrets',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Grant access to a secret
     * POST /api/v1/secrets/{name}/access/grant
     *
     * @param Request $request
     * @param string $name Secret name
     * @return JsonResponse
     */
    public function grantAccess(Request $request, string $name): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'scopes' => 'nullable|array|in:read,rotate,revoke',
                'expires_at' => 'nullable|date_format:Y-m-d H:i:s',
                'reason' => 'nullable|string',
            ]);

            $grant = $this->accessControl->grantSecretAccess(
                $validated['user_id'],
                $name,
                [
                    'scopes' => $validated['scopes'] ?? ['read'],
                    'expires_at' => $validated['expires_at'] ?? null,
                    'reason' => $validated['reason'] ?? null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Access granted successfully',
                'data' => [
                    'grant_id' => $grant->id,
                    'user_id' => $grant->user_id,
                    'secret_name' => $name,
                    'scopes' => $grant->scopes,
                    'expires_at' => $grant->expires_at,
                ],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to grant access',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Revoke access to a secret
     * DELETE /api/v1/secrets/{name}/access/{userId}
     *
     * @param Request $request
     * @param string $name Secret name
     * @param int $userId User ID
     * @return JsonResponse
     */
    public function revokeAccess(Request $request, string $name, int $userId): JsonResponse
    {
        try {
            $this->accessControl->revokeSecretAccess($userId, $name);

            return response()->json([
                'success' => true,
                'message' => 'Access revoked successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke access',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get secret accessors
     * GET /api/v1/secrets/{name}/access
     *
     * @param Request $request
     * @param string $name Secret name
     * @return JsonResponse
     */
    public function getAccessors(Request $request, string $name): JsonResponse
    {
        try {
            $accessors = $this->accessControl->getSecretAccessors($name);

            return response()->json([
                'success' => true,
                'data' => $accessors,
                'count' => $accessors->count(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get accessors',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get upcoming rotations
     * GET /api/v1/secrets/rotations/upcoming
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function upcomingRotations(Request $request): JsonResponse
    {
        try {
            $daysAhead = $request->query('days_ahead', 30);
            $rotations = $this->rotationManager->getUpcomingRotations($daysAhead);

            return response()->json([
                'success' => true,
                'data' => $rotations,
                'count' => $rotations->count(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get upcoming rotations',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
