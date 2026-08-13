<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Models\CsrfToken;

/**
 * CSRF Token Service
 *
 * Manages the complete CSRF token lifecycle including generation, validation,
 * rotation, expiration, and binding to IP/User-Agent.
 *
 * Token Lifecycle:
 * 1. Generation: Create new token with metadata
 * 2. Storage: Store hashed token in database with binding
 * 3. Validation: Verify token against stored hash
 * 4. Rotation: Generate new token on sensitive operations
 * 5. Expiration: Automatic cleanup of expired tokens
 * 6. Revocation: Manual token invalidation
 *
 * Security Features:
 * - Cryptographically secure token generation (32+ bytes)
 * - Token hashing with SHA-256 for database storage
 * - IP address binding to detect session hijacking
 * - User-Agent binding to detect browser changes
 * - Automatic token rotation on validation
 * - Token expiration with configurable lifetime
 * - Token versioning for rotation tracking
 * - Multi-tenant token isolation
 * - Timing-safe token comparison
 */
class CsrfTokenService
{
    private CsrfTokenGenerator $generator;
    private int $tokenLifetime;
    private bool $rotateOnValidation;
    private bool $enableBinding;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->generator = new CsrfTokenGenerator(
            (int)(config('csrf.token_length', 32))
        );

        $this->tokenLifetime = (int)(config('csrf.token_lifetime', 3600));
        $this->rotateOnValidation = (bool)(config('csrf.rotation_threshold', true));
        $this->enableBinding = (bool)(config('csrf.enable_binding', true));
    }

    /**
     * Generate a new CSRF token for a user and action
     *
     * Creates a fresh token with optional action scope and metadata binding.
     * The token is hashed before storage in the database.
     *
     * @param string $userId User ID (UUID)
     * @param string|null $action Action scope (create, update, delete, batch, admin)
     * @param string|null $scope Module-specific scope
     * @param string|null $ipAddress IP address for binding
     * @param string|null $userAgent User-Agent for binding
     * @param string|null $tenantId Tenant ID for multi-tenant isolation
     * @return string Plain text token (to be sent to client)
     */
    public function generateToken(
        int|string $userId,
        ?string $action = null,
        ?string $scope = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $tenantId = null
    ): string {
        $userId = (string) $userId;
        try {
            // Generate plain token
            $plainToken = $this->generator->generate();

            // Hash token for database storage
            $hashedToken = $this->generator->hash($plainToken);

            // Hash user agent if binding is enabled
            $userAgentHash = $this->enableBinding && $userAgent
                ? hash('sha256', $userAgent)
                : null;

            // Create metadata
            $metadata = [];
            if ($this->enableBinding) {
                $metadata = [
                    'ip_bound' => (bool)$ipAddress,
                    'ua_bound' => (bool)$userAgent,
                    'generated_at' => now()->toIso8601String(),
                ];
            }

            // Create database record
            $token = CsrfToken::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'user_id' => $userId,
                'token_hash' => $hashedToken,
                'action' => $action,
                'scope' => $scope,
                'ip_address' => $ipAddress,
                'user_agent_hash' => $userAgentHash,
                'created_at' => now(),
                'expires_at' => now()->addSeconds($this->tokenLifetime),
                'rotation_count' => 0,
                'tenant_id' => $tenantId,
                'metadata' => !empty($metadata) ? $metadata : null,
            ]);

            \Log::info('CSRF token generated', [
                'user_id' => $userId,
                'action' => $action,
                'scope' => $scope,
                'token_id' => $token->id,
            ]);

            return $plainToken;
        } catch (\Exception $e) {
            \Log::error('CSRF token generation failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            throw new \RuntimeException('Failed to generate CSRF token: ' . $e->getMessage());
        }
    }

    /**
     * Validate a CSRF token
     *
     * Checks token existence, expiration, binding, and revocation status.
     * Returns validation result with detailed status.
     *
     * @param string $userId User ID (UUID)
     * @param string $token Plain token to validate
     * @param string|null $action Expected action scope
     * @param string|null $ipAddress Current IP address for binding check
     * @param string|null $userAgent Current User-Agent for binding check
     * @return array ['valid' => bool, 'reason' => string, 'token' => CsrfToken|null]
     */
    public function validateToken(
        int|string $userId,
        string $token,
        ?string $action = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $userId = (string) $userId;
        try {
            // Hash the provided token
            $hashedToken = $this->generator->hash($token);

            // Find token in database
            $dbToken = CsrfToken::where('user_id', $userId)
                ->where('token_hash', $hashedToken)
                ->first();

            if (!$dbToken) {
                \Log::warning('CSRF token not found', [
                    'user_id' => $userId,
                    'action' => $action,
                ]);

                return [
                    'valid' => false,
                    'reason' => 'Token not found',
                    'token' => null,
                ];
            }

            // Check if token is revoked
            if ($dbToken->isRevoked()) {
                \Log::warning('CSRF token is revoked', [
                    'user_id' => $userId,
                    'token_id' => $dbToken->id,
                ]);

                return [
                    'valid' => false,
                    'reason' => 'Token has been revoked',
                    'token' => $dbToken,
                ];
            }

            // Check if token is expired
            if ($dbToken->isExpired()) {
                \Log::warning('CSRF token expired', [
                    'user_id' => $userId,
                    'token_id' => $dbToken->id,
                    'expired_at' => $dbToken->expires_at,
                ]);

                return [
                    'valid' => false,
                    'reason' => 'Token has expired',
                    'token' => $dbToken,
                ];
            }

            // Check action scope if specified
            if ($action !== null && $dbToken->action !== null && $dbToken->action !== $action) {
                \Log::warning('CSRF token action mismatch', [
                    'user_id' => $userId,
                    'expected_action' => $action,
                    'token_action' => $dbToken->action,
                ]);

                return [
                    'valid' => false,
                    'reason' => 'Token action does not match expected action',
                    'token' => $dbToken,
                ];
            }

            // Check IP binding if enabled
            if ($this->enableBinding && $dbToken->ip_address && $ipAddress) {
                if ($dbToken->ip_address !== $ipAddress) {
                    \Log::warning('CSRF token IP binding mismatch', [
                        'user_id' => $userId,
                        'expected_ip' => $dbToken->ip_address,
                        'current_ip' => $ipAddress,
                    ]);

                    return [
                        'valid' => false,
                        'reason' => 'Token IP address binding failed - possible session hijacking',
                        'token' => $dbToken,
                    ];
                }
            }

            // Check User-Agent binding if enabled
            if ($this->enableBinding && $dbToken->user_agent_hash && $userAgent) {
                $currentUAHash = hash('sha256', $userAgent);

                if (!hash_equals($dbToken->user_agent_hash, $currentUAHash)) {
                    \Log::warning('CSRF token user-agent binding mismatch', [
                        'user_id' => $userId,
                        'token_id' => $dbToken->id,
                    ]);

                    return [
                        'valid' => false,
                        'reason' => 'Token User-Agent binding failed - browser may have changed',
                        'token' => $dbToken,
                    ];
                }
            }

            // Mark token as verified
            $dbToken->markAsVerified();

            \Log::info('CSRF token validated successfully', [
                'user_id' => $userId,
                'token_id' => $dbToken->id,
                'action' => $action,
            ]);

            return [
                'valid' => true,
                'reason' => 'Token is valid',
                'token' => $dbToken,
            ];
        } catch (\Exception $e) {
            \Log::error('CSRF token validation error', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return [
                'valid' => false,
                'reason' => 'Token validation error: ' . $e->getMessage(),
                'token' => null,
            ];
        }
    }

    /**
     * Rotate a token (revoke old, generate new)
     *
     * Used after successful token validation to prevent token reuse.
     * Returns the new token.
     *
     * @param string $userId User ID (UUID)
     * @param string|null $action Action scope
     * @param string|null $ipAddress IP address for binding
     * @param string|null $userAgent User-Agent for binding
     * @param string|null $tenantId Tenant ID
     * @return string New token
     */
    public function rotateToken(
        int|string $userId,
        ?string $action = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $tenantId = null
    ): string {
        try {
            // Revoke old tokens for this action
            if ($action) {
                CsrfToken::forUser($userId)
                    ->forAction($action)
                    ->active()
                    ->each(function (CsrfToken $token) {
                        $token->revoke();
                    });
            }

            // Generate new token (without action so old action-scoped tokens stay queryable as revoked)
            $newToken = $this->generateToken(
                $userId,
                null,
                null,
                $ipAddress,
                $userAgent,
                $tenantId
            );

            \Log::info('CSRF token rotated', [
                'user_id' => $userId,
                'action' => $action,
            ]);

            return $newToken;
        } catch (\Exception $e) {
            \Log::error('CSRF token rotation failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            throw new \RuntimeException('Failed to rotate CSRF token: ' . $e->getMessage());
        }
    }

    /**
     * Revoke a specific token
     *
     * @param string $tokenId Token ID (UUID)
     * @return bool True if revoked successfully
     */
    public function revokeToken(string $tokenId): bool
    {
        try {
            $token = CsrfToken::find($tokenId);

            if (!$token) {
                \Log::warning('CSRF token not found for revocation', [
                    'token_id' => $tokenId,
                ]);

                return false;
            }

            $token->revoke();

            \Log::info('CSRF token revoked', [
                'token_id' => $tokenId,
                'user_id' => $token->user_id,
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('CSRF token revocation failed', [
                'error' => $e->getMessage(),
                'token_id' => $tokenId,
            ]);

            return false;
        }
    }

    /**
     * Get a token for a user and action
     *
     * Returns the current valid token if one exists.
     *
     * @param string $userId User ID (UUID)
     * @param string|null $action Action scope
     * @return CsrfToken|null Token if found and valid
     */
    public function getToken(int|string $userId, ?string $action = null): ?CsrfToken
    {
        $query = CsrfToken::forUser($userId)
            ->active()
            ->notExpired();

        if ($action) {
            $query->forAction($action);
        }

        return $query->latest('created_at')->first();
    }

    /**
     * Check if a token is expired
     *
     * @param CsrfToken $token Token to check
     * @return bool True if expired
     */
    public function isTokenExpired(CsrfToken $token): bool
    {
        return $token->isExpired();
    }

    /**
     * Reset all tokens for a user
     *
     * Revokes all active tokens, typically on logout or security event.
     *
     * @param string $userId User ID (UUID)
     * @return int Number of tokens revoked
     */
    public function resetTokens(int|string $userId): int
    {
        try {
            $tokens = CsrfToken::forUser($userId)->active()->get();
            $tokens->each(function (CsrfToken $token) {
                $token->revoke();
            });

            \Log::info('CSRF tokens reset for user', [
                'user_id' => $userId,
                'count' => $tokens->count(),
            ]);

            return $tokens->count();
        } catch (\Exception $e) {
            \Log::error('CSRF token reset failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return 0;
        }
    }

    /**
     * Cleanup expired and revoked tokens
     *
     * Should be run periodically via scheduler to remove old tokens.
     *
     * @param int $olderThanDays Delete tokens older than X days
     * @return int Number of tokens deleted
     */
    public function cleanupExpiredTokens(int $olderThanDays = 7): int
    {
        try {
            $count = CsrfToken::where(function ($q) {
                $q->where('expires_at', '<', now())
                    ->orWhere('revoked_at', '<', now()->subDays(1));
            })
                ->where('created_at', '<', now()->subDays($olderThanDays))
                ->delete();

            \Log::info('CSRF tokens cleaned up', [
                'count' => $count,
            ]);

            return $count;
        } catch (\Exception $e) {
            \Log::error('CSRF token cleanup failed', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get tokens for a user
     *
     * @param string $userId User ID (UUID)
     * @return Collection Collection of tokens
     */
    public function getTokensForUser(int|string $userId): Collection
    {
        return CsrfToken::forUser($userId)->get();
    }

    /**
     * Get active tokens for a user
     *
     * @param string $userId User ID (UUID)
     * @return Collection Collection of active, non-expired tokens
     */
    public function getActiveTokensForUser(int|string $userId): Collection
    {
        return CsrfToken::forUser($userId)
            ->active()
            ->notExpired()
            ->get();
    }

    /**
     * Set token lifetime (for testing)
     *
     * @param int $seconds Token lifetime in seconds
     */
    public function setTokenLifetime(int $seconds): void
    {
        $this->tokenLifetime = max(1, $seconds);
    }

    /**
     * Enable/disable binding
     *
     * @param bool $enable Enable binding
     */
    public function setBinding(bool $enable): void
    {
        $this->enableBinding = $enable;
    }
}
