<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use RuntimeException;

/**
 * KeyManagementService: supplies versioned master key material for
 * EncryptionService's per-context HKDF derivation.
 *
 * Derives from APP_KEY rather than a dedicated stored key: an attacker who
 * has APP_KEY already owns Laravel's own Crypt::/encrypted-cast pipeline
 * (34 PII-bearing models via App\Traits\EncryptableTrait — see
 * docs/09-RBAC-SECURITE/STANDARDS-SECURITE-MADAGASCAR.md), so tying the
 * secrets vault to the same root key adds no new attack surface, and this
 * deployment has no KMS/HSM to justify the operational cost of a separately
 * managed key. config('secrets.encryption.master_key')/SECRETS_MASTER_KEY
 * is kept as an escape hatch for a dedicated key later without touching
 * EncryptionService's public API.
 *
 * Versioning: getCurrentVersion() is the version new encryptions use.
 * getKeyMaterial() is a pure function of ($master, $version) — every past
 * version is re-derivable forever with no storage of its own, so rotating
 * to a new version (bumping SECRETS_KEY_VERSION) never invalidates
 * ciphertext encrypted under an older version, whose version number travels
 * inside EncryptionService's own envelope.
 */
class KeyManagementService
{
    /** @var array<int, string> */
    private array $materialCache = [];

    public function getCurrentVersion(): int
    {
        return max(1, (int) config('secrets.encryption.current_key_version', 1));
    }

    public function hasVersion(int $version): bool
    {
        return $version >= 1 && $version <= $this->getCurrentVersion();
    }

    public function getKeyMaterial(int $version): string
    {
        if (! isset($this->materialCache[$version])) {
            $master = $this->resolveMasterSecret();

            $this->materialCache[$version] = hash_hkdf(
                'sha256',
                $master,
                32,
                "life-mdg-erp:secrets:key:v{$version}",
                ''
            );
        }

        return $this->materialCache[$version];
    }

    private function resolveMasterSecret(): string
    {
        $master = config('secrets.encryption.master_key') ?: config('app.key');

        if (empty($master)) {
            throw new RuntimeException('KeyManagementService: no master key material available (APP_KEY is empty).');
        }

        if (str_starts_with($master, 'base64:')) {
            $decoded = base64_decode(substr($master, 7), true);

            if ($decoded === false) {
                throw new RuntimeException('KeyManagementService: master key has an invalid base64: prefix.');
            }

            return $decoded;
        }

        return $master;
    }
}
