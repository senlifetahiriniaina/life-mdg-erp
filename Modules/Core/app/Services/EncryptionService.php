<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;

/**
 * EncryptionService: per-context authenticated encryption for the secrets
 * vault (SecretsService), backed by real AES-256-CBC — not the AES-256-GCM
 * this module's docblocks/config previously (and incorrectly) claimed, see
 * docs/09-RBAC-SECURITE/STANDARDS-SECURITE-MADAGASCAR.md.
 *
 * Reuses Illuminate\Encryption\Encrypter (the same class backing this
 * repo's existing 38-site Crypt:: convention, e.g. App\Traits\EncryptableTrait)
 * rather than hand-rolled openssl_* calls: it already handles random-IV
 * generation, HMAC-SHA256 tamper detection with constant-time comparison,
 * and throws DecryptException cleanly on any MAC mismatch — exactly the
 * "wrong context fails loudly, never returns garbage" behavior this vault
 * needs, without re-implementing primitives that are easy to get subtly
 * wrong (timing-safe compare, IV reuse, etc).
 *
 * Context binding: CBC+HMAC has no AAD channel, so the "$context" a caller
 * passes to encrypt()/decrypt() (e.g. "secret.{$name}") is bound by
 * *deriving the encryption key from it* — a wrong context yields a wrong
 * subkey, so the MAC check fails. Do not "optimize" this into a plaintext
 * header field instead; that would remove the only enforcement.
 *
 * Envelope format: "SEC1.<keyVersion>.<encrypterPayload>" — 3 dot-separated
 * segments (the Encrypter payload is base64, so it contains no '.',
 * making explode(..., 3) unambiguous). getKeyVersion()/isEncrypted() parse
 * segment 2 as pure string inspection — no DB lookup, no key derivation.
 */
class EncryptionService
{
    private const ENVELOPE_PREFIX = 'SEC1';

    public function __construct(private readonly KeyManagementService $keyManagement) {}

    public function encrypt(string $value, string $context, ?int $userId): string
    {
        $version = $this->keyManagement->getCurrentVersion();
        $encrypter = $this->encrypterFor($version, $context);

        $payload = $encrypter->encryptString($value);

        return self::ENVELOPE_PREFIX.'.'.$version.'.'.$payload;
    }

    public function decrypt(string $encrypted, string $context, ?int $userId): string
    {
        [$version, $payload] = $this->parseEnvelope($encrypted);

        $encrypter = $this->encrypterFor($version, $context);

        return $encrypter->decryptString($payload);
    }

    /**
     * Pure string parse — the version travels in the ciphertext itself so
     * this never needs the row it came from.
     */
    public function getKeyVersion(string $encrypted): int
    {
        return $this->parseEnvelope($encrypted)[0];
    }

    public function isEncrypted(string $value): bool
    {
        $parts = explode('.', $value, 3);

        if (count($parts) !== 3 || $parts[0] !== self::ENVELOPE_PREFIX || ! ctype_digit($parts[1])) {
            return false;
        }

        $decoded = base64_decode($parts[2], true);

        if ($decoded === false) {
            return false;
        }

        $json = json_decode($decoded, true);

        return is_array($json) && isset($json['iv'], $json['value'], $json['mac']);
    }

    /**
     * @return array{0: int, 1: string} [keyVersion, encrypterPayload]
     */
    private function parseEnvelope(string $encrypted): array
    {
        $parts = explode('.', $encrypted, 3);

        if (count($parts) !== 3 || $parts[0] !== self::ENVELOPE_PREFIX || ! ctype_digit($parts[1])) {
            throw new DecryptException('Malformed secrets-vault ciphertext envelope.');
        }

        return [(int) $parts[1], $parts[2]];
    }

    private function encrypterFor(int $version, string $context): Encrypter
    {
        $material = $this->keyManagement->getKeyMaterial($version);

        $subkey = hash_hkdf('sha256', $material, 32, "life-mdg-erp:secrets:ctx:{$context}", '');

        return new Encrypter($subkey, config('app.cipher'));
    }
}
