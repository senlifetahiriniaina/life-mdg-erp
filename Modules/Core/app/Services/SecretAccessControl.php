<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Models\ApiKey;
use Modules\Core\Models\Secret;
use Modules\Core\Models\SecretAccessGrant;
use App\Models\User;

/**
 * SecretAccessControl: Fine-grained access control for secrets
 *
 * Manages role-based and per-user access to secrets, including:
 * - User permission grants with scope control (read, rotate, revoke)
 * - API key generation for service accounts
 * - Automatic expiration management
 * - Access verification and audit
 */
class SecretAccessControl
{
    private AuditService $audit;

    public function __construct(AuditService $audit)
    {
        $this->audit = $audit;
    }

    /**
     * Check if a user can access a specific secret
     *
     * @param int|null $userId User ID
     * @param string $secretName Secret name
     * @param string $scope Required scope (read, rotate, revoke)
     * @return bool True if user can access
     */
    public function canAccessSecret(?int $userId, string $secretName, string $scope = 'read'): bool
    {
        if ($userId === null) {
            return false;
        }

        try {
            $user = User::find($userId);
            if (!$user) {
                return false;
            }

            // Admins can always access
            if ($user->hasRole(['admin', 'super-admin'])) {
                return true;
            }

            // Secrets stored by a user with no explicit tenant_id land under
            // the string 'default' (SecretsService::getTenantId()'s own
            // fallback) — mirror that here, or a null tenant_id would never
            // match and every such secret would be invisible to its owner.
            $tenantId = $user->tenant_id ?? 'default';

            // Get secret
            $secret = Secret::where('tenant_id', $tenantId)
                ->where('name', $secretName)
                ->active()
                ->first();

            if (!$secret) {
                return false;
            }

            // Check explicit grant
            $grant = SecretAccessGrant::where('secret_id', $secret->id)
                ->where('user_id', $userId)
                ->active()
                ->first();

            if ($grant && $grant->hasScope($scope)) {
                return true;
            }

            // Check role-based access if configured
            if ($this->checkRoleBasedAccess($user, $secret, $scope)) {
                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error('Access control check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Grant access to a secret for a specific user
     *
     * @param int $userId User ID to grant access
     * @param string $secretName Secret name
     * @param array $options Grant options (scopes, expires_at, reason)
     * @return SecretAccessGrant
     * @throws Exception
     */
    public function grantSecretAccess(int $userId, string $secretName, array $options = []): SecretAccessGrant
    {
        try {
            $tenantId = $this->getTenantId();
            $grantedBy = auth()->id() ?? 1;

            // Verify grantor is admin
            $grantor = User::find($grantedBy);
            if (!$grantor || !$grantor->hasRole(['admin', 'super-admin'])) {
                throw new Exception('Only administrators can grant secret access');
            }

            // Get secret
            $secret = Secret::where('tenant_id', $tenantId)
                ->where('name', $secretName)
                ->first();

            if (!$secret) {
                throw new Exception("Secret '{$secretName}' not found");
            }

            // Revoke existing grant if present
            $existing = SecretAccessGrant::where('secret_id', $secret->id)
                ->where('user_id', $userId)
                ->active()
                ->first();

            if ($existing) {
                $existing->revoke();
            }

            // Create new grant
            $grant = SecretAccessGrant::create([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'secret_id' => $secret->id,
                'user_id' => $userId,
                'scopes' => $options['scopes'] ?? ['read'],
                'expires_at' => $options['expires_at'] ?? null,
                'granted_by' => $grantedBy,
                'reason' => $options['reason'] ?? null,
            ]);

            // Log grant
            $this->audit->log(
                action: 'secret_access.granted',
                userId: $grantedBy,
                module: 'Core',
                eventType: 'secret_access.granted',
                newValues: [
                    'secret_id' => $secret->id,
                    'secret_name' => $secret->name,
                    'user_id' => $userId,
                    'scopes' => implode(',', $options['scopes'] ?? ['read']),
                ],
            );

            Log::info("Secret access granted", [
                'secret_name' => $secretName,
                'user_id' => $userId,
                'scopes' => implode(',', $options['scopes'] ?? ['read']),
            ]);

            return $grant;
        } catch (Exception $e) {
            Log::error("Failed to grant secret access: {$secretName}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Revoke access to a secret for a specific user
     *
     * @param int $userId User ID to revoke access
     * @param string $secretName Secret name
     * @throws Exception
     */
    public function revokeSecretAccess(int $userId, string $secretName): void
    {
        try {
            $tenantId = $this->getTenantId();

            $secret = Secret::where('tenant_id', $tenantId)
                ->where('name', $secretName)
                ->first();

            if (!$secret) {
                throw new Exception("Secret '{$secretName}' not found");
            }

            $grant = SecretAccessGrant::where('secret_id', $secret->id)
                ->where('user_id', $userId)
                ->active()
                ->first();

            if (!$grant) {
                throw new Exception("No active grant found for user {$userId}");
            }

            $grant->revoke();

            // Log revocation
            $this->audit->log(
                action: 'secret_access.revoked',
                userId: auth()->id(),
                module: 'Core',
                eventType: 'secret_access.revoked',
                newValues: [
                    'secret_id' => $secret->id,
                    'secret_name' => $secret->name,
                    'user_id' => $userId,
                ],
            );

            Log::info("Secret access revoked", [
                'secret_name' => $secretName,
                'user_id' => $userId,
            ]);
        } catch (Exception $e) {
            Log::error("Failed to revoke secret access: {$secretName}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get all secrets a user can access
     *
     * @param int $userId User ID
     * @return Collection Collection of accessible secrets with metadata
     */
    public function getUserSecretAccess(int $userId): Collection
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return collect();
            }

            // Same 'default'-fallback mismatch as canAccessSecret() above.
            $tenantId = $user->tenant_id ?? 'default';

            // Get explicit grants
            $secretIds = SecretAccessGrant::where('user_id', $userId)
                ->active()
                ->pluck('secret_id');

            $secrets = Secret::where('tenant_id', $tenantId)
                ->whereIn('id', $secretIds)
                ->active()
                ->get();

            return $secrets->map(function (Secret $secret) use ($userId) {
                $grant = SecretAccessGrant::where('secret_id', $secret->id)
                    ->where('user_id', $userId)
                    ->active()
                    ->first();

                return [
                    'id' => $secret->id,
                    'name' => $secret->name,
                    'type' => $secret->type,
                    'scopes' => $grant?->scopes ?? [],
                    'expires_at' => $grant?->expires_at,
                    'granted_at' => $grant?->created_at,
                ];
            });
        } catch (Exception $e) {
            Log::error('Failed to get user secret access', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Get all users who can access a specific secret
     *
     * @param string $secretName Secret name
     * @return Collection Collection of users with their access grants
     */
    public function getSecretAccessors(string $secretName): Collection
    {
        try {
            $tenantId = $this->getTenantId();

            $secret = Secret::where('tenant_id', $tenantId)
                ->where('name', $secretName)
                ->first();

            if (!$secret) {
                return collect();
            }

            return SecretAccessGrant::where('secret_id', $secret->id)
                ->active()
                ->with('user')
                ->get()
                ->map(function (SecretAccessGrant $grant) {
                    return [
                        'user_id' => $grant->user_id,
                        'user_name' => $grant->user->name,
                        'user_email' => $grant->user->email,
                        'scopes' => $grant->scopes,
                        'expires_at' => $grant->expires_at,
                        'granted_at' => $grant->created_at,
                        'granted_by' => $grant->grantedBy->name,
                    ];
                });
        } catch (Exception $e) {
            Log::error('Failed to get secret accessors', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Generate an API key for a service account
     *
     * @param string $name API key name/description
     * @param array $options API key options (scopes, expires_at, ip_restrictions)
     * @return array Array with key (only shown once) and key_id
     * @throws Exception
     */
    public function generateApiKey(string $name, array $options = []): array
    {
        try {
            $tenantId = $this->getTenantId();
            $userId = auth()->id() ?? 1;

            // Check API key limit
            $keyCount = ApiKey::where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->active()
                ->count();

            $maxKeys = config('secrets.api_keys.max_keys_per_user', 10);
            if ($keyCount >= $maxKeys) {
                throw new Exception("API key limit ({$maxKeys}) reached");
            }

            // Generate key
            $key = 'sk_' . Str::random(40);
            $keyHash = Hash::make($key);
            $keyPreview = substr($key, 0, 10);

            // Create API key record
            $apiKey = ApiKey::create([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'name' => $name,
                'key_hash' => $keyHash,
                'key_preview' => $keyPreview,
                'scopes' => $options['scopes'] ?? ['read'],
                'ip_restrictions' => $options['ip_restrictions'] ?? null,
                'expires_at' => $options['expires_at'] ?? $this->getDefaultApiKeyExpiration(),
                'is_active' => true,
            ]);

            // Log generation
            $this->audit->log(
                action: 'api_key.generated',
                userId: $userId,
                module: 'Core',
                eventType: 'api_key.generated',
                newValues: [
                    'api_key_id' => $apiKey->id,
                    'api_key_name' => $apiKey->name,
                    'scopes' => implode(',', $options['scopes'] ?? ['read']),
                ],
            );

            Log::info("API key generated: {$name}", ['api_key_id' => $apiKey->id]);

            return [
                'key_id' => $apiKey->id,
                'key' => $key, // Only shown once
                'key_preview' => $keyPreview,
                'scopes' => $apiKey->scopes,
                'expires_at' => $apiKey->expires_at,
            ];
        } catch (Exception $e) {
            Log::error("Failed to generate API key: {$name}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Verify an API key and record usage
     *
     * @param string $key The API key to verify
     * @param string $ip The client IP address
     * @return bool|array False if invalid, array with key info if valid
     */
    public function verifyApiKey(string $key, string $ip = ''): bool|array
    {
        try {
            // Find key by hash (expensive operation, should be cached)
            $keys = ApiKey::active()->get();

            foreach ($keys as $apiKey) {
                if (Hash::check($key, $apiKey->key_hash)) {
                    // Check IP restrictions
                    if (!$apiKey->checkIpRestriction($ip)) {
                        return false;
                    }

                    // Record usage
                    $apiKey->recordUsage();

                    return [
                        'key_id' => $apiKey->id,
                        'user_id' => $apiKey->user_id,
                        'tenant_id' => $apiKey->tenant_id,
                        'scopes' => $apiKey->scopes,
                    ];
                }
            }

            return false;
        } catch (Exception $e) {
            Log::error('API key verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Revoke an API key
     *
     * @param string $keyId API key ID
     * @throws Exception
     */
    public function revokeApiKey(string $keyId): void
    {
        try {
            $apiKey = ApiKey::find($keyId);
            if (!$apiKey) {
                throw new Exception("API key not found");
            }

            $apiKey->deactivate();

            // Log revocation
            $this->audit->log(
                action: 'api_key.revoked',
                userId: auth()->id(),
                module: 'Core',
                eventType: 'api_key.revoked',
                newValues: [
                    'api_key_id' => $apiKey->id,
                    'api_key_name' => $apiKey->name,
                ],
            );

            Log::info("API key revoked: {$apiKey->name}", ['api_key_id' => $keyId]);
        } catch (Exception $e) {
            Log::error("Failed to revoke API key", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Check role-based access to a secret
     *
     * @param User $user The user
     * @param Secret $secret The secret
     * @param string $scope Required scope
     * @return bool True if user has role-based access
     */
    private function checkRoleBasedAccess(User $user, Secret $secret, string $scope): bool
    {
        // This can be extended to support role-based secret access policies
        // For now, only explicit grants or admin roles work

        return false;
    }

    /**
     * Get the default expiration for API keys
     *
     * @return string|null ISO 8601 date string
     */
    private function getDefaultApiKeyExpiration(): ?string
    {
        $ttl = config('secrets.api_keys.default_ttl', 365);
        return now()->addDays($ttl)->toDateTimeString();
    }

    /**
     * Get the current tenant ID
     *
     * @return string
     */
    private function getTenantId(): string
    {
        return auth()->user()?->tenant_id ?? request()->header('X-Tenant-ID', 'default');
    }
}
