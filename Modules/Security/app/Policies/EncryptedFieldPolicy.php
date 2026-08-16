<?php

namespace Modules\Security\Policies;

use App\Models\User;
use Modules\Security\Models\EncryptedField;

/**
 * EncryptedField is part of the encryption subsystem (which fields/tables
 * are marked encrypted, and with which key) -- reuses the security.encryption.*
 * permissions rather than minting a separate encrypted_field resource.
 */
class EncryptedFieldPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('security.encryption.view');
    }

    public function view(User $user, EncryptedField $encryptedField): bool
    {
        if (!$user->hasPermissionTo('security.encryption.view')) {
            return false;
        }

        return $user->company_id === $encryptedField->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('security.encryption.create');
    }

    public function update(User $user, EncryptedField $encryptedField): bool
    {
        if (!$user->hasPermissionTo('security.encryption.update')) {
            return false;
        }

        return $user->company_id === $encryptedField->company_id;
    }

    public function delete(User $user, EncryptedField $encryptedField): bool
    {
        if (!$user->hasPermissionTo('security.encryption.delete')) {
            return false;
        }

        return $user->company_id === $encryptedField->company_id;
    }
}
