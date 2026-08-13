<?php

declare(strict_types=1);

namespace Modules\AI\Policies;

use App\Models\User;
use Modules\AI\Models\AiInsight;

class AiInsightPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AiInsight $aiInsight): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['ai-analyst', 'admin']);
    }

    public function update(User $user, AiInsight $aiInsight): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function delete(User $user, AiInsight $aiInsight): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function restore(User $user, AiInsight $aiInsight): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function forceDelete(User $user, AiInsight $aiInsight): bool
    {
        return $user->hasRole('super-admin');
    }
}
