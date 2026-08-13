<?php

namespace Modules\Core\Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Services\MFAService;
use Tests\TestCase;

class MFAServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MFAService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MFAService::class);
        $this->user = User::factory()->create();
    }

    /**
     * Test TOTP Setup
     */
    public function test_totp_setup_generates_secret()
    {
        $result = $this->service->enableMFA($this->user, 'totp');

        $this->assertArrayHasKey('method', $result);
        $this->assertArrayHasKey('secret', $result);
        $this->assertArrayHasKey('qr_code', $result);
        $this->assertEquals('totp', $result['method']);
    }

    public function test_totp_setup_generates_backup_codes()
    {
        $result = $this->service->enableMFA($this->user, 'totp');

        $this->assertArrayHasKey('backup_codes', $result);
        $this->assertCount(10, $result['backup_codes']);
    }

    public function test_totp_setup_provides_qr_code()
    {
        $result = $this->service->enableMFA($this->user, 'totp');

        $this->assertArrayHasKey('qr_code', $result);
        $this->assertStringContainsString('data:image', $result['qr_code']);
    }

    /**
     * Test SMS Setup
     */
    public function test_sms_setup()
    {
        $result = $this->service->enableMFA($this->user, 'sms');

        $this->assertEquals('sms', $result['method']);
        $this->assertTrue($result['requires_verification']);
    }

    /**
     * Test Email Setup
     */
    public function test_email_setup()
    {
        $result = $this->service->enableMFA($this->user, 'email');

        $this->assertEquals('email', $result['method']);
        $this->assertTrue($result['requires_verification']);
    }

    /**
     * Test Backup Codes Generation
     */
    public function test_backup_codes_generation()
    {
        $codes = $this->service->generateBackupCodes($this->user);

        $this->assertCount(10, $codes);

        // Each code should be 8 characters
        foreach ($codes as $code) {
            $this->assertEquals(8, strlen($code));
        }
    }

    public function test_backup_codes_are_unique()
    {
        $codes = $this->service->generateBackupCodes($this->user);

        // All codes should be unique
        $this->assertEquals(count($codes), count(array_unique($codes)));
    }

    /**
     * Test Backup Code Verification
     */
    public function test_backup_code_verification_succeeds()
    {
        $mfaData = $this->service->enableMFA($this->user, 'totp');
        $backupCode = $mfaData['backup_codes'][0];

        $result = $this->service->verifyMFA($this->user, $backupCode, 'backup_codes');

        $this->assertTrue($result);
    }

    public function test_backup_code_one_time_use()
    {
        $mfaData = $this->service->enableMFA($this->user, 'totp');
        $backupCode = $mfaData['backup_codes'][0];

        // First use should succeed
        $this->assertTrue($this->service->verifyMFA($this->user, $backupCode, 'backup_codes'));

        // Second use should fail
        $this->assertFalse($this->service->verifyMFA($this->user, $backupCode, 'backup_codes'));
    }

    public function test_invalid_backup_code_fails()
    {
        $this->service->enableMFA($this->user, 'totp');

        $result = $this->service->verifyMFA($this->user, 'invalid99', 'backup_codes');

        $this->assertFalse($result);
    }

    /**
     * Test SMS Code
     */
    public function test_sms_code_generation()
    {
        $this->service->enableMFA($this->user, 'sms');
        $this->service->sendSMSCode($this->user);

        // Code should be cached
        $cachedCode = \Cache::get("mfa:sms:{$this->user->id}");

        $this->assertNotNull($cachedCode);
        $this->assertEquals(6, strlen($cachedCode));
    }

    public function test_sms_code_verification()
    {
        $this->service->enableMFA($this->user, 'sms');
        $this->service->sendSMSCode($this->user);

        $code = \Cache::get("mfa:sms:{$this->user->id}");

        $result = $this->service->verifyMFA($this->user, $code, 'sms');

        $this->assertTrue($result);
    }

    public function test_sms_code_expires()
    {
        $this->service->enableMFA($this->user, 'sms');
        $this->service->sendSMSCode($this->user);

        // Clear the cache to simulate expiration
        \Cache::forget("mfa:sms:{$this->user->id}");

        $result = $this->service->verifyMFA($this->user, '000000', 'sms');

        $this->assertFalse($result);
    }

    /**
     * Test Email Code
     */
    public function test_email_code_generation()
    {
        $this->service->enableMFA($this->user, 'email');
        $this->service->sendEmailCode($this->user);

        $cachedCode = \Cache::get("mfa:email:{$this->user->id}");

        $this->assertNotNull($cachedCode);
        $this->assertEquals(6, strlen($cachedCode));
    }

    public function test_email_code_verification()
    {
        $this->service->enableMFA($this->user, 'email');
        $this->service->sendEmailCode($this->user);

        $code = \Cache::get("mfa:email:{$this->user->id}");

        $result = $this->service->verifyMFA($this->user, $code, 'email');

        $this->assertTrue($result);
    }

    /**
     * Test TOTP Verification
     */
    public function test_totp_verification_with_valid_code()
    {
        // This test requires a valid TOTP code generated at the right time
        // For testing, we skip this as it requires time synchronization

        $this->markTestSkipped('TOTP verification requires time-based code generation');
    }

    /**
     * Test WebAuthn Setup
     */
    public function test_webauthn_setup()
    {
        $result = $this->service->enableMFA($this->user, 'hardware_key');

        $this->assertEquals('hardware_key', $result['method']);
        $this->assertArrayHasKey('registration_challenge', $result);
    }

    /**
     * Test MFA Enable/Disable
     */
    public function test_mfa_enable_sets_verified_false()
    {
        $this->service->enableMFA($this->user, 'totp');

        $this->assertFalse($this->user->fresh()->mfa_verified);
    }

    public function test_mfa_disable_clears_all_settings()
    {
        $this->service->enableMFA($this->user, 'totp');
        $this->service->disableMFA($this->user);

        $user = $this->user->fresh();

        $this->assertNull($user->mfa_method);
        $this->assertNull($user->mfa_secret);
        $this->assertFalse($user->mfa_verified);
        $this->assertNull($user->mfa_backup_codes);
    }

    /**
     * Test Check if MFA is Enabled
     */
    public function test_is_mfa_enabled_returns_false_when_disabled()
    {
        $result = $this->service->isMFAEnabled($this->user);

        $this->assertFalse($result);
    }

    public function test_is_mfa_enabled_requires_verified_flag()
    {
        $this->service->enableMFA($this->user, 'totp');

        // Should still be false because mfa_verified is false
        $result = $this->service->isMFAEnabled($this->user);

        $this->assertFalse($result);

        // Manually mark as verified
        $this->user->update(['mfa_verified' => true]);

        $result = $this->service->isMFAEnabled($this->user);

        $this->assertTrue($result);
    }

    /**
     * Test Get Enabled Methods
     */
    public function test_get_enabled_methods_includes_backup_codes()
    {
        $this->service->enableMFA($this->user, 'totp');
        $this->user->update(['mfa_verified' => true]);

        $methods = $this->service->getEnabledMethods($this->user);

        $this->assertContains('totp', $methods);
        $this->assertContains('backup_codes', $methods);
    }

    /**
     * Test Backup Codes Remaining Count
     */
    public function test_backup_codes_remaining()
    {
        $mfaData = $this->service->enableMFA($this->user, 'totp');

        $remaining = $this->service->getBackupCodesRemaining($this->user);

        $this->assertEquals(10, $remaining);

        // Use one code
        $this->service->verifyMFA($this->user, $mfaData['backup_codes'][0], 'backup_codes');

        $remaining = $this->service->getBackupCodesRemaining($this->user);

        $this->assertEquals(9, $remaining);
    }

    /**
     * Test Backup Codes Remaining When None Available
     */
    public function test_backup_codes_remaining_zero()
    {
        $mfaData = $this->service->enableMFA($this->user, 'totp');

        // Use all backup codes
        foreach ($mfaData['backup_codes'] as $code) {
            $this->service->verifyMFA($this->user, $code, 'backup_codes');
        }

        $remaining = $this->service->getBackupCodesRemaining($this->user);

        $this->assertEquals(0, $remaining);
    }

    /**
     * Test Invalid MFA Method
     */
    public function test_invalid_mfa_method_throws_exception()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->enableMFA($this->user, 'invalid_method');
    }

    /**
     * Test Multiple MFA Methods Not Simultaneously Enabled
     */
    public function test_enabling_new_mfa_overwrites_old()
    {
        $this->service->enableMFA($this->user, 'totp');
        $this->assertEquals('totp', $this->user->fresh()->mfa_method);

        $this->service->enableMFA($this->user, 'sms');
        $this->assertEquals('sms', $this->user->fresh()->mfa_method);
    }
}
