<?php

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Secret;
use Modules\Core\Models\SecretAccessGrant;
use Modules\Core\Models\ApiKey;
use Modules\Core\Services\SecretAccessControl;
use Modules\Core\Services\SecretsService;
use Modules\Core\Services\EncryptionService;
use Modules\Core\Services\KeyManagementService;
use Modules\Core\Services\AuditService;
use Tests\TestCase;

/**
 * SecretAccessControlTest: Tests for access control and authorization
 *
 * Tests permission grants, API keys, access verification, and audit logging
 */
class SecretAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private SecretAccessControl $accessControl;
    private SecretsService $secretsService;
    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user = User::factory()->create(['role' => 'user']);

        $encryption = $this->app->make(EncryptionService::class);
        $audit = $this->app->make(AuditService::class);
        $this->secretsService = new SecretsService($encryption, $audit);
        $this->accessControl = new SecretAccessControl($audit);
    }

    /**
     * Test granting secret access
     */
    public function test_grant_secret_access(): void
    {
        $this->actingAs($this->admin);

        $secret = $this->secretsService->storeSecret(
            'test_grant',
            'secret_value',
            'api_key'
        );

        $grant = $this->accessControl->grantSecretAccess(
            $this->user->id,
            'test_grant',
            ['scopes' => ['read', 'rotate']]
        );

        $this->assertNotNull($grant);
        $this->assertEquals($this->user->id, $grant->user_id);
        $this->assertTrue($grant->hasScope('read'));
        $this->assertTrue($grant->hasScope('rotate'));
    }

    /**
     * Test revoking secret access
     */
    public function test_revoke_secret_access(): void
    {
        $this->actingAs($this->admin);

        $secret = $this->secretsService->storeSecret(
            'test_revoke',
            'secret_value',
            'api_key'
        );

        $this->accessControl->grantSecretAccess($this->user->id, 'test_revoke');
        $this->accessControl->revokeSecretAccess($this->user->id, 'test_revoke');

        $grant = SecretAccessGrant::where('secret_id', $secret->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($grant->revoked_at);
    }

    /**
     * Test user cannot access denied secret
     */
    public function test_user_cannot_access_denied_secret(): void
    {
        $this->actingAs($this->admin);

        $secret = $this->secretsService->storeSecret(
            'test_denied',
            'secret_value',
            'api_key'
        );

        // Don't grant access, then try to retrieve
        $this->actingAs($this->user);

        $this->expectException(\Exception::class);
        $this->secretsService->retrieveSecret('test_denied');
    }

    /**
     * Test API key generation
     */
    public function test_generate_api_key(): void
    {
        $this->actingAs($this->user);

        $result = $this->accessControl->generateApiKey(
            'test_api_key',
            ['scopes' => ['read', 'create']]
        );

        $this->assertNotEmpty($result['key']);
        $this->assertNotEmpty($result['key_id']);
        $this->assertEquals(['read', 'create'], $result['scopes']);
    }

    /**
     * Test API key scopes are enforced
     */
    public function test_api_key_scopes_enforced(): void
    {
        $this->actingAs($this->user);

        $result = $this->accessControl->generateApiKey(
            'scoped_key',
            ['scopes' => ['read']]
        );

        $apiKey = ApiKey::find($result['key_id']);

        $this->assertTrue($apiKey->hasScope('read'));
        $this->assertFalse($apiKey->hasScope('admin'));
        $this->assertTrue($apiKey->hasAllScopes(['read']));
    }

    /**
     * Test API key expiration
     */
    public function test_api_key_expiration(): void
    {
        $this->actingAs($this->user);

        $expiresAt = now()->addDays(30)->toDateTimeString();

        $result = $this->accessControl->generateApiKey(
            'expiring_key',
            ['expires_at' => $expiresAt]
        );

        $apiKey = ApiKey::find($result['key_id']);

        $this->assertNotNull($apiKey->expires_at);
        $this->assertEquals(30, $apiKey->daysUntilExpiration());
    }

    /**
     * Test API key verification
     */
    public function test_api_key_verification(): void
    {
        $this->actingAs($this->user);

        $result = $this->accessControl->generateApiKey('verify_key');
        $key = $result['key'];

        $verified = $this->accessControl->verifyApiKey($key);

        $this->assertNotFalse($verified);
        $this->assertEquals($result['key_id'], $verified['key_id']);
        $this->assertEquals($this->user->id, $verified['user_id']);
    }

    /**
     * Test invalid API key verification fails
     */
    public function test_invalid_api_key_fails_verification(): void
    {
        $verified = $this->accessControl->verifyApiKey('sk_invalid_key');

        $this->assertFalse($verified);
    }

    /**
     * Test revoking API key
     */
    public function test_revoke_api_key(): void
    {
        $this->actingAs($this->user);

        $result = $this->accessControl->generateApiKey('revoke_key');
        $keyId = $result['key_id'];

        $this->accessControl->revokeApiKey($keyId);

        $apiKey = ApiKey::find($keyId);
        $this->assertFalse($apiKey->is_active);
    }

    /**
     * Test getting user secret access
     */
    public function test_get_user_secret_access(): void
    {
        $this->actingAs($this->admin);

        $secret1 = $this->secretsService->storeSecret('secret1', 'value1', 'api_key');
        $secret2 = $this->secretsService->storeSecret('secret2', 'value2', 'api_key');

        $this->accessControl->grantSecretAccess($this->user->id, 'secret1', ['scopes' => ['read']]);
        $this->accessControl->grantSecretAccess($this->user->id, 'secret2', ['scopes' => ['read', 'rotate']]);

        $access = $this->accessControl->getUserSecretAccess($this->user->id);

        $this->assertEquals(2, $access->count());
    }

    /**
     * Test getting secret accessors
     */
    public function test_get_secret_accessors(): void
    {
        $this->actingAs($this->admin);

        $secret = $this->secretsService->storeSecret('shared_secret', 'value', 'api_key');

        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $this->accessControl->grantSecretAccess($this->user->id, 'shared_secret');
        $this->accessControl->grantSecretAccess($user2->id, 'shared_secret');
        $this->accessControl->grantSecretAccess($user3->id, 'shared_secret');

        $accessors = $this->accessControl->getSecretAccessors('shared_secret');

        $this->assertEquals(3, $accessors->count());
    }

    /**
     * Test access control check
     */
    public function test_can_access_secret(): void
    {
        $this->actingAs($this->admin);

        $secret = $this->secretsService->storeSecret('access_test', 'value', 'api_key');

        $this->accessControl->grantSecretAccess($this->user->id, 'access_test', ['scopes' => ['read']]);

        $canAccess = $this->accessControl->canAccessSecret($this->user->id, 'access_test', 'read');
        $this->assertTrue($canAccess);

        $canRotate = $this->accessControl->canAccessSecret($this->user->id, 'access_test', 'rotate');
        $this->assertFalse($canRotate);
    }

    /**
     * Test admin always has access
     */
    public function test_admin_always_has_access(): void
    {
        $this->actingAs($this->admin);

        $secret = $this->secretsService->storeSecret('admin_test', 'value', 'api_key');

        $canAccess = $this->accessControl->canAccessSecret($this->admin->id, 'admin_test', 'read');

        $this->assertTrue($canAccess);
    }

    /**
     * Test grant expiration
     */
    public function test_grant_expiration(): void
    {
        $this->actingAs($this->admin);

        $secret = $this->secretsService->storeSecret('expiring_grant', 'value', 'api_key');

        $expiresAt = now()->addDays(7)->toDateTimeString();
        $grant = $this->accessControl->grantSecretAccess(
            $this->user->id,
            'expiring_grant',
            ['expires_at' => $expiresAt]
        );

        $this->assertEquals(7, $grant->daysUntilExpiration());
    }

    /**
     * Test non-admin cannot grant access
     */
    public function test_non_admin_cannot_grant_access(): void
    {
        $this->actingAs($this->user);

        $secret = $this->secretsService->storeSecret('grant_test', 'value', 'api_key');

        $this->expectException(\Exception::class);
        $this->accessControl->grantSecretAccess($this->user->id, 'grant_test');
    }

    /**
     * Test API key with IP restrictions
     */
    public function test_api_key_ip_restrictions(): void
    {
        $this->actingAs($this->user);

        $result = $this->accessControl->generateApiKey(
            'ip_restricted_key',
            ['ip_restrictions' => '192.168.1.1,10.0.0.1']
        );

        $apiKey = ApiKey::find($result['key_id']);

        $this->assertTrue($apiKey->checkIpRestriction('192.168.1.1'));
        $this->assertFalse($apiKey->checkIpRestriction('192.168.1.2'));
    }
}
