<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Secrets API: create failed', [
                'exception' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'secret_name' => $request->input('name'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create secret',
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
            // Chantier 10: canAccessSecret() existed (admin/super-admin bypass,
            // else an explicit SecretAccessGrant with the 'read' scope) but was
            // never called by this controller — wired in now, on top of the
            // route-level role: gate added the same pass.
            if (!$this->accessControl->canAccessSecret($request->user()?->id, $name, 'read')) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }

            $value = $this->secretsService->retrieveSecret($name);

            return response()->json([
                'success' => true,
                'data' => [
                    'name' => $name,
                    'value' => $value,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Secrets API: retrieve failed', [
                'exception' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'secret_name' => $name,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve secret',
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
            Log::error('Secrets API: metadata failed', [
                'exception' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'secret_name' => $name,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve metadata',
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
            if (!$this->accessControl->canAccessSecret($request->user()?->id, $name, 'rotate')) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }

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
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Secrets API: rotate failed', [
                'exception' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'secret_name' => $name,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to rotate secret',
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
            if (!$this->accessControl->canAccessSecret($request->user()?->id, $name, 'revoke')) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }

            $this->secretsService->revokeSecret($name);

            return response()->json([
                'success' => true,
                'message' => 'Secret revoked successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Secrets API: revoke failed', [
                'exception' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'secret_name' => $name,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke secret',
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
            Log::error('Secrets API: list failed', [
                'exception' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to list secrets',
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
                'scopes' => 'nullable|array',
                'scopes.*' => 'string|in:read,rotate,revoke',
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
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Secrets API: grant access failed', [
                'exception' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'secret_name' => $name,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to grant access',
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
            Log::error('Secrets API: revoke access failed', [
                'exception' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'secret_name' => $name,
                'target_user_id' => $userId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke access',
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
            Log::error('Secrets API: get accessors failed', [
                'exception' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'secret_name' => $name,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get accessors',
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
            Log::error('Secrets API: get upcoming rotations failed', [
                'exception' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get upcoming rotations',
            ], 400);
        }
    }
}
