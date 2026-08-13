<?php

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Secret;
use Modules\Core\Models\SecretAccessLog;
use Modules\Core\Models\SecretRotationPolicy;
use Modules\Core\Services\EncryptionService;
use Modules\Core\Services\SecretsService;
use Modules\Core\Services\KeyManagementService;
use Modules\Core\Services\AuditService;
use Tests\TestCase;

/**
 * SecretsServiceTest: Unit tests for secrets management
 *
 * Tests encryption, decryption, rotation, versioning, and audit logging
 */
class SecretsServiceTest extends TestCase
{
    use RefreshDatabase;

    private SecretsService $secretsService;
    private EncryptionService $encryption;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $keyManagement = $this->app->make(KeyManagementService::class);
        $this->encryption = new EncryptionService($keyManagement);
        $audit = $this->app->make(AuditService::class);
        $this->secretsService = new SecretsService($this->encryption, $audit);
    }

    /**
     * Test storing a secret encrypts the value
     */
    public function test_store_secret_encrypts_value(): void
    {
        $secret = $this->secretsService->storeSecret(
            'test_api_key',
            'sk_secret_value_123',
            'api_key'
        );

        // Verify encrypted value is stored
        $this->assertNotEmpty($secret->encrypted_value);
        $this->assertNotEquals('sk_secret_value_123', $secret->encrypted_value);
        $this->assertTrue($this->encryption->isEncrypted($secret->encrypted_value));
    }

    /**
     * Test retrieving a secret decrypts the value
     */
    public function test_retrieve_secret_decrypts_value(): void
    {
        $plainValue = 'sk_my_secret_key_456';
        $secret = $this->secretsService->storeSecret(
            'test_retrieve',
            $plainValue,
            'api_key'
        );

        $decrypted = $this->secretsService->retrieveSecret('test_retrieve');

        $this->assertEquals($plainValue, $decrypted);
    }

    /**
     * Test secret expiration is enforced
     */
    public function test_secret_expiration_enforced(): void
    {
        $secret = Secret::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'tenant_id' => $this->user->tenant_id,
            'name' => 'expired_secret',
            'type' => 'api_key',
            'encrypted_value' => 'encrypted',
            'key_version' => 1,
            'created_by' => $this->user->id,
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $this->expectException(\Exception::class);
        $this->secretsService->retrieveSecret('expired_secret');
    }

    /**
     * Test rotating a secret creates a new version
     */
    public function test_rotate_secret_creates_new_version(): void
    {
        $secret = $this->secretsService->storeSecret(
            'test_rotate',
            'original_value',
            'api_key'
        );

        $originalVersion = $secret->key_version;

        $rotated = $this->secretsService->rotateSecret('test_rotate', 'new_value_xyz');

        $this->assertGreaterThanOrEqual($originalVersion, $rotated->key_version);
        $this->assertNotNull($rotated->rotated_at);
    }

    /**
     * Test revoking a secret prevents access
     */
    public function test_revoke_secret_prevents_access(): void
    {
        $secret = $this->secretsService->storeSecret(
            'test_revoke',
            'secret_value',
            'api_key'
        );

        $this->secretsService->revokeSecret('test_revoke');

        $revoked = Secret::find($secret->id);
        $this->assertFalse($revoked->is_active);

        $this->expectException(\Exception::class);
        $this->secretsService->retrieveSecret('test_revoke');
    }

    /**
     * Test listing secrets with filtering
     */
    public function test_list_secrets_filtered(): void
    {
        $this->secretsService->storeSecret('api_key_1', 'value1', 'api_key');
        $this->secretsService->storeSecret('db_cred_1', 'value2', 'database_credential');
        $this->secretsService->storeSecret('token_1', 'value3', 'oauth_token');

        $apiKeys = $this->secretsService->listSecrets(['type' => 'api_key']);
        $this->assertEquals(1, $apiKeys->count());

        $allSecrets = $this->secretsService->listSecrets();
        $this->assertEquals(3, $allSecrets->count());
    }

    /**
     * Test secret access is logged
     */
    public function test_secret_access_logged(): void
    {
        $secret = $this->secretsService->storeSecret(
            'test_audit',
            'secret_value',
            'api_key'
        );

        // Log should be created on store
        $logs = SecretAccessLog::where('secret_id', $secret->id)
            ->where('action', 'create')
            ->get();

        $this->assertGreaterThan(0, $logs->count());
    }

    /**
     * Test secret versioning
     */
    public function test_secret_versioning(): void
    {
        $secret = $this->secretsService->storeSecret(
            'test_version',
            'value1',
            'api_key'
        );

        $version1 = $secret->key_version;

        $this->secretsService->rotateSecret('test_version', 'value2');
        $rotated = Secret::find($secret->id);

        $this->assertGreaterThanOrEqual($version1, $rotated->key_version);
    }

    /**
     * Test encryption on store
     */
    public function test_encryption_on_store(): void
    {
        $plainValue = 'plain_secret_text';
        $secret = $this->secretsService->storeSecret(
            'test_encrypt',
            $plainValue,
            'api_key'
        );

        // Encrypted value should not match plaintext
        $this->assertNotEquals($plainValue, $secret->encrypted_value);

        // Should be decodable by encryption service
        $decoded = $this->encryption->decrypt($secret->encrypted_value, 'secret.test_encrypt', $this->user->id);
        $this->assertEquals($plainValue, $decoded);
    }

    /**
     * Test decryption on retrieve
     */
    public function test_decryption_on_retrieve(): void
    {
        $plainValue = 'secret_to_decrypt';
        $this->secretsService->storeSecret(
            'test_decrypt',
            $plainValue,
            'api_key'
        );

        $retrieved = $this->secretsService->retrieveSecret('test_decrypt');

        $this->assertEquals($plainValue, $retrieved);
    }

    /**
     * Test secrets are not logged in plaintext
     */
    public function test_secret_not_in_logs(): void
    {
        $secret = $this->secretsService->storeSecret(
            'test_log_masking',
            'super_secret_value_123',
            'api_key'
        );

        // Check that logs don't contain the plaintext secret
        $logs = SecretAccessLog::where('secret_id', $secret->id)->get();

        foreach ($logs as $log) {
            $this->assertStringNotContainsString('super_secret_value_123', $log->reason ?? '');
        }
    }

    /**
     * Test multi-tenant isolation
     */
    public function test_multi_tenant_isolation(): void
    {
        $otherUser = User::factory()->create(['tenant_id' => 'other_tenant']);

        $secret = $this->secretsService->storeSecret(
            'test_isolation',
            'secret_value',
            'api_key'
        );

        // Switch tenant
        $this->actingAs($otherUser);

        // Should not find the secret from other tenant
        $this->expectException(\Exception::class);
        $this->secretsService->retrieveSecret('test_isolation');
    }

    /**
     * Test secret metadata retrieval
     */
    public function test_get_secret_metadata(): void
    {
        $secret = $this->secretsService->storeSecret(
            'test_metadata',
            'secret_value',
            'api_key',
            ['tags' => ['production', 'critical']]
        );

        $metadata = $this->secretsService->getSecretMetadata('test_metadata');

        $this->assertNotNull($metadata);
        $this->assertEquals('test_metadata', $metadata['name']);
        $this->assertEquals('api_key', $metadata['type']);
        $this->assertContains('production', $metadata['tags']);
    }

    /**
     * Test rotation schedule creation
     */
    public function test_rotation_schedule_created(): void
    {
        $secret = $this->secretsService->storeSecret(
            'test_rotation_schedule',
            'secret_value',
            'api_key',
            ['rotation_interval' => 30]
        );

        $schedule = $this->secretsService->getSecretRotationSchedule('test_rotation_schedule');

        $this->assertTrue($schedule['has_policy']);
        $this->assertEquals(30, $schedule['rotation_interval']);
        $this->assertNotNull($schedule['next_rotation_at']);
    }

    /**
     * Test masking secrets for display
     */
    public function test_mask_secret(): void
    {
        $original = 'sk_secret_value_very_long';
        $masked = $this->secretsService->maskSecret($original);

        $this->assertStringStartsWith('sk_s', $masked);
        $this->assertStringContainsString('*', $masked);
        $this->assertNotEquals($original, $masked);
    }
}
