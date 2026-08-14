<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Unit;

use Illuminate\Contracts\Encryption\DecryptException;
use Modules\Core\Services\EncryptionService;
use Modules\Core\Services\KeyManagementService;
use Tests\TestCase;

/**
 * EncryptionService's own contract — independent of SecretsService, which
 * only exercises it indirectly. None of this was covered before Phase 5,
 * because the class didn't exist.
 */
class EncryptionServiceTest extends TestCase
{
    protected EncryptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EncryptionService(app(KeyManagementService::class));
    }

    public function test_encrypt_decrypt_round_trip()
    {
        $plaintext = 'sk_super_secret_value';

        $encrypted = $this->service->encrypt($plaintext, 'secret.alpha', 1);

        $this->assertNotEquals($plaintext, $encrypted);
        $this->assertEquals($plaintext, $this->service->decrypt($encrypted, 'secret.alpha', 1));
    }

    public function test_encrypting_the_same_value_twice_yields_different_ciphertexts()
    {
        $plaintext = 'sk_super_secret_value';

        $first = $this->service->encrypt($plaintext, 'secret.alpha', 1);
        $second = $this->service->encrypt($plaintext, 'secret.alpha', 1);

        $this->assertNotEquals($first, $second, 'IV must be random per encryption call');
    }

    public function test_decrypt_with_wrong_context_throws()
    {
        $encrypted = $this->service->encrypt('sk_super_secret_value', 'secret.alpha', 1);

        $this->expectException(DecryptException::class);

        $this->service->decrypt($encrypted, 'secret.beta', 1);
    }

    public function test_key_version_recoverable_from_ciphertext_alone()
    {
        $encrypted = $this->service->encrypt('sk_super_secret_value', 'secret.alpha', 1);

        $keyManagement = app(KeyManagementService::class);

        $this->assertEquals($keyManagement->getCurrentVersion(), $this->service->getKeyVersion($encrypted));
    }

    public function test_is_encrypted_discriminates_real_envelopes_from_plaintext()
    {
        $encrypted = $this->service->encrypt('sk_super_secret_value', 'secret.alpha', 1);

        $this->assertTrue($this->service->isEncrypted($encrypted));
        $this->assertFalse($this->service->isEncrypted('encrypted'));
        $this->assertFalse($this->service->isEncrypted(''));
        $this->assertFalse($this->service->isEncrypted('sk_plain_api_key_value'));
        $this->assertFalse($this->service->isEncrypted(encrypt('a plain Laravel Crypt:: payload')));
    }

    public function test_tampered_ciphertext_is_rejected()
    {
        $encrypted = $this->service->encrypt('sk_super_secret_value', 'secret.alpha', 1);

        [$prefix, $version, $payload] = explode('.', $encrypted, 3);
        $tampered = $prefix.'.'.$version.'.'.substr($payload, 0, -4).'abcd';

        $this->expectException(DecryptException::class);

        $this->service->decrypt($tampered, 'secret.alpha', 1);
    }

    public function test_old_key_version_still_decrypts_after_a_version_bump()
    {
        $encrypted = $this->service->encrypt('sk_super_secret_value', 'secret.alpha', 1);
        $originalVersion = $this->service->getKeyVersion($encrypted);

        config(['secrets.encryption.current_key_version' => $originalVersion + 1]);

        // New KeyManagementService instance so it doesn't reuse a version
        // cached before the config change.
        $service = new EncryptionService(new KeyManagementService());

        $this->assertEquals('sk_super_secret_value', $service->decrypt($encrypted, 'secret.alpha', 1));
    }
}
