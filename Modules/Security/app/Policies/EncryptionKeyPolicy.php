<?php

namespace Modules\Security\Policies;

use App\Models\User;
use Modules\Security\Models\EncryptionKey;

class EncryptionKeyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('security.encryption.view');
    }

    public function view(User $user, EncryptionKey $encryptionKey): bool
    {
        if (!$user->hasPermissionTo('security.encryption.view')) {
            return false;
        }

        return (string) $user->company_id === (string) $encryptionKey->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('security.encryption.create');
    }

    public function update(User $user, EncryptionKey $encryptionKey): bool
    {
        if (!$user->hasPermissionTo('security.encryption.update')) {
            return false;
        }

        return (string) $user->company_id === (string) $encryptionKey->company_id;
    }

    public function rotate(User $user, EncryptionKey $encryptionKey): bool
    {
        if (!$user->hasPermissionTo('security.encryption.rotate')) {
            return false;
        }

        return (string) $user->company_id === (string) $encryptionKey->company_id && $encryptionKey->key_status === 'active';
    }

    public function revoke(User $user, EncryptionKey $encryptionKey): bool
    {
        if (!$user->hasPermissionTo('security.encryption.delete')) {
            return false;
        }

        return (string) $user->company_id === (string) $encryptionKey->company_id && $encryptionKey->key_status !== 'revoked';
    }

    public function delete(User $user, EncryptionKey $encryptionKey): bool
    {
        if (!$user->hasPermissionTo('security.encryption.delete')) {
            return false;
        }

        return (string) $user->company_id === (string) $encryptionKey->company_id && $encryptionKey->key_status === 'revoked';
    }
}
