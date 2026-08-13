<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

trait EncryptableTrait
{
    /**
     * Attributes to transparently encrypt. Declared here so that models setting
     * `$this->encrypted = [...]` assign this property instead of creating a bogus
     * Eloquent attribute (which would be persisted as a non-existent column).
     *
     * @var array<int, string>
     */
    protected array $encrypted = [];

    public function setAttribute($key, $value): self
    {
        if ($this->isEncryptable($key) && !is_null($value) && !$this->isEncrypted($value)) {
            $value = Crypt::encryptString($value);
        }

        return parent::setAttribute($key, $value);
    }

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if ($this->isEncryptable($key) && !is_null($value)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Exception $e) {
                // If decryption fails, return original value
                // (might be unencrypted legacy data)
            }
        }

        return $value;
    }

    protected function isEncryptable(string $key): bool
    {
        return property_exists($this, 'encrypted') && in_array($key, $this->encrypted, true);
    }

    protected function isEncrypted(string $value): bool
    {
        // Encrypted values are base64-encoded and start with specific prefix
        return str_starts_with($value, 'eyJp') && str_contains($value, ':');
    }

    public function encryptAttributes(): self
    {
        if (property_exists($this, 'encrypted')) {
            foreach ($this->encrypted as $attribute) {
                if (isset($this->attributes[$attribute])) {
                    $this->setAttribute($attribute, $this->attributes[$attribute]);
                }
            }
        }

        return $this;
    }
}
