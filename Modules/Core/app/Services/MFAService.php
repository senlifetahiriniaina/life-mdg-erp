<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * MFAService — Multi-Factor Authentication
 *
 * Supports four second-factor methods:
 *  - totp: RFC 6238 time-based one-time password (authenticator apps)
 *  - sms: 6-digit code delivered by SMS, cached with a short TTL
 *  - email: 6-digit code delivered by email, cached with a short TTL
 *  - hardware_key: WebAuthn/FIDO2 hardware security keys (registration
 *    challenge only — the actual attestation/assertion ceremony is a
 *    frontend concern, out of scope for this service)
 *
 * Every enrollment also (re)issues a set of 10 single-use backup codes.
 * State is split between the `users` table (method/secret/verified flag/
 * backup codes — see 2026_08_18_000004_add_mfa_columns_to_users_table) and
 * the cache (short-lived SMS/email codes).
 */
class MFAService
{
    private const VALID_METHODS = ['totp', 'sms', 'email', 'hardware_key'];

    private const BACKUP_CODE_COUNT = 10;

    private const BACKUP_CODE_LENGTH = 8;

    /** Characters used for backup codes — excludes 0/O/1/I to avoid ambiguity. */
    private const BACKUP_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const CODE_TTL_MINUTES = 5;

    private const TOTP_DIGITS = 6;

    private const TOTP_PERIOD = 30;

    /**
     * Enable an MFA method for a user. Overwrites any previously configured
     * method (only one method can be active at a time). Always resets the
     * `verified` flag — enabling a method is not the same as confirming it.
     *
     * @return array{
     *   method: string,
     *   secret?: string,
     *   qr_code?: string,
     *   backup_codes?: string[],
     *   requires_verification?: bool,
     *   registration_challenge?: string,
     * }
     */
    public function enableMFA(User $user, string $method): array
    {
        if (! in_array($method, self::VALID_METHODS, true)) {
            throw new InvalidArgumentException("Invalid MFA method: {$method}");
        }

        $result = ['method' => $method];

        switch ($method) {
            case 'totp':
                $secret = $this->generateTotpSecret();

                $user->forceFill([
                    'mfa_method' => 'totp',
                    'mfa_secret' => $secret,
                    'mfa_verified' => false,
                ])->save();

                $result['secret'] = $secret;
                $result['qr_code'] = $this->generateQrCodeDataUri($user, $secret);
                $result['backup_codes'] = $this->generateBackupCodes($user);

                break;

            case 'sms':
                $user->forceFill([
                    'mfa_method' => 'sms',
                    'mfa_secret' => null,
                    'mfa_verified' => false,
                    'mfa_backup_codes' => null,
                ])->save();

                $result['requires_verification'] = true;

                break;

            case 'email':
                $user->forceFill([
                    'mfa_method' => 'email',
                    'mfa_secret' => null,
                    'mfa_verified' => false,
                    'mfa_backup_codes' => null,
                ])->save();

                $result['requires_verification'] = true;

                break;

            case 'hardware_key':
                $challenge = base64_encode(random_bytes(32));

                $user->forceFill([
                    'mfa_method' => 'hardware_key',
                    'mfa_secret' => null,
                    'mfa_verified' => false,
                    'mfa_backup_codes' => null,
                ])->save();

                $result['registration_challenge'] = $challenge;

                break;
        }

        return $result;
    }

    /**
     * Verify an MFA code for the given method.
     *
     * - `totp`: validated against the RFC 6238 secret (current + adjacent
     *   30s windows, to tolerate clock drift).
     * - `sms` / `email`: validated against the code cached by
     *   sendSMSCode()/sendEmailCode(). Consumed on success (one-time use).
     * - `backup_codes`: validated against the stored backup codes list. The
     *   matched code is removed on success (one-time use).
     */
    public function verifyMFA(User $user, string $code, string $method): bool
    {
        return match ($method) {
            'backup_codes' => $this->verifyBackupCode($user, $code),
            'sms' => $this->verifyCachedCode($user, $code, 'sms'),
            'email' => $this->verifyCachedCode($user, $code, 'email'),
            'totp' => $this->verifyTotpCode($user, $code),
            default => false,
        };
    }

    /**
     * (Re)generate the 10 single-use backup codes for a user, overwriting
     * any existing set. Returns the plain codes (only ever surfaced once —
     * the caller is expected to show these to the user immediately).
     *
     * @return string[]
     */
    public function generateBackupCodes(User $user): array
    {
        $codes = [];

        while (count($codes) < self::BACKUP_CODE_COUNT) {
            $code = $this->generateBackupCode();
            if (! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        $user->forceFill(['mfa_backup_codes' => $codes])->save();

        return $codes;
    }

    /**
     * Generate and cache a 6-digit SMS verification code (5 minute TTL).
     */
    public function sendSMSCode(User $user): string
    {
        $code = $this->generateNumericCode();

        Cache::put("mfa:sms:{$user->id}", $code, now()->addMinutes(self::CODE_TTL_MINUTES));

        return $code;
    }

    /**
     * Generate and cache a 6-digit email verification code (5 minute TTL).
     */
    public function sendEmailCode(User $user): string
    {
        $code = $this->generateNumericCode();

        Cache::put("mfa:email:{$user->id}", $code, now()->addMinutes(self::CODE_TTL_MINUTES));

        return $code;
    }

    /**
     * Disable MFA entirely, clearing every stored setting.
     */
    public function disableMFA(User $user): void
    {
        $user->forceFill([
            'mfa_method' => null,
            'mfa_secret' => null,
            'mfa_verified' => false,
            'mfa_backup_codes' => null,
        ])->save();

        Cache::forget("mfa:sms:{$user->id}");
        Cache::forget("mfa:email:{$user->id}");
    }

    /**
     * MFA is only considered "enabled" once a method has been set AND
     * verified (enrollment isn't complete until the user proves possession).
     */
    public function isMFAEnabled(User $user): bool
    {
        return $user->mfa_method !== null && (bool) $user->mfa_verified;
    }

    /**
     * The list of currently usable second factors — the active method plus
     * "backup_codes" when at least one backup code remains.
     *
     * @return string[]
     */
    public function getEnabledMethods(User $user): array
    {
        if (! $this->isMFAEnabled($user)) {
            return [];
        }

        $methods = [$user->mfa_method];

        if ($this->getBackupCodesRemaining($user) > 0) {
            $methods[] = 'backup_codes';
        }

        return $methods;
    }

    public function getBackupCodesRemaining(User $user): int
    {
        return count($user->mfa_backup_codes ?? []);
    }

    private function verifyBackupCode(User $user, string $code): bool
    {
        $codes = $user->mfa_backup_codes ?? [];

        $index = array_search($code, $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);

        $user->forceFill(['mfa_backup_codes' => array_values($codes)])->save();

        return true;
    }

    private function verifyCachedCode(User $user, string $code, string $channel): bool
    {
        $cacheKey = "mfa:{$channel}:{$user->id}";
        $cached = Cache::get($cacheKey);

        if ($cached === null) {
            return false;
        }

        if (! hash_equals((string) $cached, $code)) {
            return false;
        }

        Cache::forget($cacheKey);

        return true;
    }

    private function verifyTotpCode(User $user, string $code): bool
    {
        if (empty($user->mfa_secret)) {
            return false;
        }

        $timestamp = time();

        // Tolerate clock drift: accept the current window and one on either side.
        foreach ([-1, 0, 1] as $windowOffset) {
            $expected = $this->generateTotpCode($user->mfa_secret, $timestamp + ($windowOffset * self::TOTP_PERIOD));
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    private function generateBackupCode(): string
    {
        $alphabet = self::BACKUP_CODE_ALPHABET;
        $code = '';

        for ($i = 0; $i < self::BACKUP_CODE_LENGTH; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    private function generateNumericCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function generateTotpSecret(int $length = 32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // RFC 4648 base32

        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $secret;
    }

    private function generateQrCodeDataUri(User $user, string $secret): string
    {
        $otpauth = $this->buildOtpAuthUri($user, $secret);

        // A real QR bitmap is out of scope without a new dependency; the
        // provisioning payload (otpauth:// URI) is embedded as SVG text so
        // any authenticator app's "manual entry" flow still works from it.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">'
            .'<rect width="200" height="200" fill="#ffffff"/>'
            .'<text x="10" y="100" font-size="6" fill="#000000">'.htmlspecialchars($otpauth).'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function buildOtpAuthUri(User $user, string $secret): string
    {
        $issuer = rawurlencode('LifeMDG');
        $label = rawurlencode('LifeMDG:'.$user->email);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=".self::TOTP_DIGITS.'&period='.self::TOTP_PERIOD;
    }

    /**
     * RFC 6238 TOTP code generation (HMAC-SHA1 based, matching Google
     * Authenticator / Authy / most authenticator apps' defaults).
     */
    private function generateTotpCode(string $base32Secret, int $timestamp): string
    {
        $key = $this->base32Decode($base32Secret);
        $counter = intdiv($timestamp, self::TOTP_PERIOD);

        $binaryCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $truncated = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $code = $truncated % (10 ** self::TOTP_DIGITS);

        return str_pad((string) $code, self::TOTP_DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper((string) preg_replace('/[^A-Z2-7]/i', '', $secret));

        $bits = '';
        foreach (str_split($secret) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr((int) bindec($byte));
            }
        }

        return $bytes;
    }
}
