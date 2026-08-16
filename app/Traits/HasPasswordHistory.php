<?php

namespace App\Traits;

use Illuminate\Support\Facades\Hash;

/**
 * OWASP password-reuse prevention: keeps the last N password hashes per user
 * and rejects re-use of any of them. Never stores plaintext -- only the same
 * kind of hash already stored on users.password.
 */
trait HasPasswordHistory
{
    public function passwordHistories()
    {
        return $this->hasMany(\App\Models\PasswordHistory::class)->latest('created_at');
    }

    public function recordPasswordHistory(string $hashedPassword): void
    {
        $this->passwordHistories()->create(['password_hash' => $hashedPassword]);

        $limit = (int) config('auth.password_history_limit', 5);
        $keep = $this->passwordHistories()->limit($limit)->pluck('id');
        $this->passwordHistories()->whereNotIn('id', $keep)->delete();
    }

    public function wasPasswordUsedBefore(string $plainPassword): bool
    {
        $limit = (int) config('auth.password_history_limit', 5);

        return $this->passwordHistories()
            ->limit($limit)
            ->get()
            ->contains(fn ($history) => Hash::check($plainPassword, $history->password_hash));
    }
}
