<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Models\Secret;
use Modules\Core\Models\SecretAccessLog;
use Modules\Core\Models\SecretRotationPolicy;
use App\Models\User;

/**
 * SecretsService: Centralized secrets management with encryption, versioning, and rotation
 *
 * Provides secure storage and retrieval of secrets (API keys, tokens, credentials)
 * with full encryption, audit logging, versioning, and rotation support.
 *
 * Security Features:
 * - AES-256-CBC encryption at rest (via EncryptionService)
 * - Secret versioning and rotation with verification
 * - Comprehensive audit logging of all access
 * - Multi-tenant isolation
 * - Expiration management with notifications
 * - Rate limiting on access
 * - Secret masking in logs
 */
class SecretsService
{
    private EncryptionService $encryption;
    private AuditService $audit;

    public function __construct(EncryptionService $encryption, AuditService $audit)
    {
        $this->encryption = $encryption;
        $this->audit = $audit;
    }

    /**
     * Store a new secret with encryption and versioning
     *
     * @param string $name Unique secret name
     * @param string $value Secret value to encrypt
     * @param string $type Secret type (api_key, oauth_token, db_credential, ssh_key, certificate)
     * @param ?array $options Additional options (expires_at, tags, rotation_interval)
     * @return Secret The stored secret
     * @throws Exception
     */
    public function storeSecret(
        string $name,
        string $value,
        string $type,
        ?array $options = null
    ): Secret {
        try {
            $tenantId = $this->getTenantId();
            $userId = auth()->id() ?? 1;

            // Validate secret doesn't already exist
            $existing = Secret::where('tenant_id', $tenantId)
                ->where('name', $name)
                ->first();

            if ($existing) {
                throw new Exception("Secret '{$name}' already exists in this tenant");
            }

            // Encrypt the value
            $encryptedValue = $this->encryption->encrypt(
                $value,
                "secret.{$name}",
                $userId
            );

            // Create secret record
            $secret = Secret::create([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => $name,
                'type' => $type,
                'encrypted_value' => $encryptedValue,
                'key_version' => $this->encryption->getKeyVersion($encryptedValue),
                'created_by' => $userId,
                'expires_at' => $options['expires_at'] ?? $this->getDefaultExpiration(),
                'tags' => $options['tags'] ?? null,
                'is_active' => true,
            ]);

            // Create rotation policy if specified
            if (isset($options['rotation_interval']) || config('secrets.rotation.auto_rotate_enabled')) {
                $this->createRotationPolicy($secret, $options['rotation_interval'] ?? null);
            }

            // Log creation
            $this->logAccess($secret, 'create', true);
            $this->audit->log(
                action: 'secret.created',
                userId: $userId,
                module: 'Core',
                eventType: 'secret.created',
                newValues: [
                    'secret_id' => $secret->id,
                    'secret_name' => $secret->name,
                    'secret_type' => $secret->type,
                ],
            );

            Log::info("Secret created: {$name}", ['secret_id' => $secret->id]);

            return $secret;
        } catch (Exception $e) {
            Log::error("Failed to store secret: {$name}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Retrieve a secret value with decryption and access control
     *
     * @param string $name Secret name
     * @return string|null The decrypted secret value
     * @throws Exception
     */
    public function retrieveSecret(string $name): ?string
    {
        try {
            $tenantId = $this->getTenantId();
            $userId = auth()->id();

            // Get secret
            $secret = Secret::where('tenant_id', $tenantId)
                ->where('name', $name)
                ->active()
                ->notExpired()
                ->first();

            if (!$secret) {
                $this->logAccess(null, 'access_denied', false, "Secret not found: {$name}");
                throw new Exception("Secret '{$name}' not found or has expired");
            }

            // Check access control
            if (!app(SecretAccessControl::class)->canAccessSecret($userId, $name)) {
                $this->logAccess($secret, 'access_denied', false, 'Access denied');
                throw new Exception('Insufficient permissions to access this secret');
            }

            // Check rate limiting
            if (!$this->checkRateLimit($userId)) {
                $this->logAccess($secret, 'access_denied', false, 'Rate limit exceeded');
                throw new Exception('Rate limit exceeded for secret access');
            }

            // Decrypt value
            $value = $this->encryption->decrypt(
                $secret->encrypted_value,
                "secret.{$name}",
                $userId
            );

            // Log access
            $this->logAccess($secret, 'retrieve', true);

            return (string) $value;
        } catch (Exception $e) {
            Log::error("Failed to retrieve secret: {$name}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Rotate a secret with verification and optional rollback
     *
     * @param string $name Secret name
     * @param string|null $newValue New secret value (optional, can be generated)
     * @return Secret The rotated secret
     * @throws Exception
     */
    public function rotateSecret(string $name, ?string $newValue = null): Secret
    {
        try {
            $tenantId = $this->getTenantId();
            $userId = auth()->id();

            // Get secret
            $secret = Secret::where('tenant_id', $tenantId)
                ->where('name', $name)
                ->active()
                ->first();

            if (!$secret) {
                throw new Exception("Secret '{$name}' not found");
            }

            // Store old value for verification/rollback
            $oldValue = $this->encryption->decrypt(
                $secret->encrypted_value,
                "secret.{$name}",
                $userId
            );

            // Generate new value if not provided
            if ($newValue === null) {
                $newValue = $this->generateSecretValue($secret->type);
            }

            // Encrypt new value with new version
            $newEncrypted = $this->encryption->encrypt(
                $newValue,
                "secret.{$name}",
                $userId
            );

            // Store rotation
            $oldEncrypted = $secret->encrypted_value;
            $secret->update([
                'encrypted_value' => $newEncrypted,
                'rotated_at' => now(),
                'key_version' => $this->encryption->getKeyVersion($newEncrypted),
            ]);

            // Verify rotation
            if (config('secrets.security.verify_rotation')) {
                $verified = $this->verifyRotation($secret, $oldValue, $newValue);
                if (!$verified && config('secrets.security.enable_rollback')) {
                    // Rollback on verification failure
                    $secret->update(['encrypted_value' => $oldEncrypted]);
                    throw new Exception('Rotation verification failed, rolled back to previous value');
                }
            }

            // Update rotation policy
            if ($secret->rotationPolicy) {
                $secret->rotationPolicy->markRotated();
            }

            // Log rotation
            $this->logAccess($secret, 'rotate', true);
            $this->audit->log(
                action: 'secret.rotated',
                userId: $userId,
                module: 'Core',
                eventType: 'secret.rotated',
                newValues: [
                    'secret_id' => $secret->id,
                    'secret_name' => $secret->name,
                ],
            );

            Log::info("Secret rotated: {$name}", ['secret_id' => $secret->id]);

            return $secret;
        } catch (Exception $e) {
            Log::error("Failed to rotate secret: {$name}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Revoke a secret, preventing further access
     *
     * @param string $name Secret name
     * @return void
     * @throws Exception
     */
    public function revokeSecret(string $name): void
    {
        try {
            $tenantId = $this->getTenantId();

            $secret = Secret::where('tenant_id', $tenantId)
                ->where('name', $name)
                ->first();

            if (!$secret) {
                throw new Exception("Secret '{$name}' not found");
            }

            $secret->update([
                'is_active' => false,
            ]);

            // Log revocation
            $this->logAccess($secret, 'revoke', true);
            $this->audit->log(
                action: 'secret.revoked',
                userId: auth()->id(),
                module: 'Core',
                eventType: 'secret.revoked',
                newValues: [
                    'secret_id' => $secret->id,
                    'secret_name' => $secret->name,
                ],
            );

            Log::info("Secret revoked: {$name}", ['secret_id' => $secret->id]);
        } catch (Exception $e) {
            Log::error("Failed to revoke secret: {$name}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * List secrets with optional filtering and pagination
     *
     * @param array $filters Optional filters (type, tags, active_only)
     * @return Collection
     */
    public function listSecrets(array $filters = []): Collection
    {
        $tenantId = $this->getTenantId();
        $query = Secret::where('tenant_id', $tenantId);

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['tags'])) {
            $query->whereJsonContains('tags', $filters['tags']);
        }

        if ($filters['active_only'] ?? false) {
            $query->active();
        }

        if (!empty($filters['exclude_expired'])) {
            $query->notExpired();
        }

        return $query->get();
    }

    /**
     * Get metadata about a secret without retrieving its value
     *
     * @param string $name Secret name
     * @return array|null Metadata (type, created_at, expires_at, rotated_at, tags)
     */
    public function getSecretMetadata(string $name): ?array
    {
        $tenantId = $this->getTenantId();

        $secret = Secret::where('tenant_id', $tenantId)
            ->where('name', $name)
            ->first();

        if (!$secret) {
            return null;
        }

        return [
            'id' => $secret->id,
            'name' => $secret->name,
            'type' => $secret->type,
            'type_label' => $secret->getSecretType(),
            'created_at' => $secret->created_at,
            'updated_at' => $secret->updated_at,
            'expires_at' => $secret->expires_at,
            'rotated_at' => $secret->rotated_at,
            'next_rotation' => $secret->next_rotation,
            'is_active' => $secret->is_active,
            'is_expired' => $secret->isExpired(),
            'days_until_expiration' => $secret->daysUntilExpiration(),
            'days_until_rotation' => $secret->daysUntilRotation(),
            'tags' => $secret->tags,
            'created_by' => $secret->createdBy?->name,
            'key_version' => $secret->key_version,
        ];
    }

    /**
     * Get the rotation schedule for a secret
     *
     * @param string $name Secret name
     * @return array Schedule information
     */
    public function getSecretRotationSchedule(string $name): array
    {
        $tenantId = $this->getTenantId();

        $secret = Secret::where('tenant_id', $tenantId)
            ->where('name', $name)
            ->with('rotationPolicy')
            ->first();

        if (!$secret || !$secret->rotationPolicy) {
            return [
                'has_policy' => false,
                'auto_rotate' => false,
            ];
        }

        $policy = $secret->rotationPolicy;

        return [
            'has_policy' => true,
            'auto_rotate' => $policy->auto_rotate,
            'rotation_interval' => $policy->rotation_interval,
            'last_rotation_at' => $policy->last_rotation_at,
            'next_rotation_at' => $policy->next_rotation_at,
            'days_until_rotation' => $policy->daysUntilRotation(),
            'notification_days' => $policy->notification_days_before,
        ];
    }

    /**
     * Verify that a rotation was successful by comparing old and new values
     *
     * @param Secret $secret The secret being rotated
     * @param string $oldValue The old secret value
     * @param string $newValue The new secret value
     * @return bool True if verification passes
     */
    private function verifyRotation(Secret $secret, string $oldValue, string $newValue): bool
    {
        try {
            // Verify values are different
            if ($oldValue === $newValue) {
                Log::warning('Rotation verification failed: values are identical', [
                    'secret_id' => $secret->id,
                ]);
                return false;
            }

            // Can add additional verification logic here
            // For example, validate format, length, complexity, etc.

            return true;
        } catch (Exception $e) {
            Log::error('Rotation verification error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Generate a secret value based on type
     *
     * @param string $type Secret type
     * @return string Generated secret value
     */
    private function generateSecretValue(string $type): string
    {
        return match ($type) {
            'api_key' => 'sk_' . Str::random(32),
            'oauth_token' => 'token_' . Str::random(40),
            'database_credential' => Str::random(32),
            'ssh_key' => 'key_' . Str::random(32),
            'certificate' => Str::random(48),
            default => Str::random(32),
        };
    }

    /**
     * Create a rotation policy for a secret
     *
     * @param Secret $secret The secret to rotate
     * @param int|null $interval Rotation interval in days
     */
    private function createRotationPolicy(Secret $secret, ?int $interval = null): void
    {
        $interval = $interval ?? config('secrets.rotation.default_interval', 30);

        SecretRotationPolicy::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $secret->tenant_id,
            'secret_id' => $secret->id,
            'rotation_interval' => $interval,
            'next_rotation_at' => now()->addDays($interval),
            'auto_rotate' => config('secrets.rotation.auto_rotate_enabled', true),
            'notification_days_before' => array_map(
                'intval',
                explode(',', config('secrets.rotation.notification_days_before', '7,14,30'))
            ),
        ]);
    }

    /**
     * Get the default expiration date for secrets
     *
     * @return string|null ISO 8601 date string or null for no expiration
     */
    private function getDefaultExpiration(): ?string
    {
        if (!config('secrets.expiration.enable_expiration')) {
            return null;
        }

        $ttl = config('secrets.expiration.default_ttl', 90);
        return now()->addDays($ttl)->toDateTimeString();
    }

    /**
     * Check rate limiting for secret access
     *
     * @param int $userId User ID
     * @return bool True if within rate limit
     */
    private function checkRateLimit(int $userId): bool
    {
        $limit = config('secrets.access_control.rate_limit', 60);
        $window = config('secrets.access_control.rate_limit_window', 60);

        $cacheKey = "secrets:rate_limit:{$userId}";
        $count = Cache::get($cacheKey, 0);

        if ($count >= $limit) {
            return false;
        }

        Cache::put($cacheKey, $count + 1, $window);
        return true;
    }

    /**
     * Log secret access/operations
     *
     * @param Secret|null $secret The secret being accessed
     * @param string $action Action performed (retrieve, create, rotate, revoke, access_denied)
     * @param bool $success Whether the action was successful
     * @param string|null $reason Reason for failure
     */
    private function logAccess(?Secret $secret, string $action, bool $success, ?string $reason = null): void
    {
        if (!config('secrets.audit.enabled')) {
            return;
        }

        try {
            SecretAccessLog::create([
                'id' => Str::uuid()->toString(),
                'tenant_id' => $secret?->tenant_id ?? $this->getTenantId(),
                'secret_id' => $secret?->id,
                'user_id' => auth()->id() ?? 1,
                'action' => $action,
                'ip_address' => request()->ip(),
                'success' => $success,
                'reason' => $reason,
                'timestamp' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to log secret access', ['error' => $e->getMessage()]);
        }
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

    /**
     * Mask a secret value for display
     *
     * @param string $value The secret value
     * @return string Masked value
     */
    public function maskSecret(string $value): string
    {
        if (!config('secrets.masking.enabled')) {
            return $value;
        }

        $showChars = config('secrets.masking.show_chars', 4);
        $maskChar = config('secrets.masking.mask_char', '*');

        if (strlen($value) <= $showChars) {
            return str_repeat($maskChar, strlen($value));
        }

        $visible = substr($value, 0, $showChars);
        $masked = str_repeat($maskChar, strlen($value) - $showChars);

        return $visible . $masked;
    }
}
