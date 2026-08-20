<?php

declare(strict_types=1);

namespace Modules\Messaging\Policies;

use App\Models\User;
use Modules\Messaging\Models\Conversation;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->participants()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function post(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
