<?php

declare(strict_types=1);

namespace Modules\AI\Policies;

use App\Models\User;
use Modules\AI\Models\AiRequest;

class AiRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['ai-analyst', 'admin', 'super-admin']);
    }

    public function view(User $user, AiRequest $aiRequest): bool
    {
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        return $user->hasRole('ai-analyst') && (int) $aiRequest->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['ai-analyst', 'admin', 'super-admin']);
    }

    public function update(User $user, AiRequest $aiRequest): bool
    {
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        return (int) $aiRequest->user_id === $user->id;
    }

    public function delete(User $user, AiRequest $aiRequest): bool
    {
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        return (int) $aiRequest->user_id === $user->id;
    }

    public function restore(User $user, AiRequest $aiRequest): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function forceDelete(User $user, AiRequest $aiRequest): bool
    {
        return $user->hasRole('super-admin');
    }
}
