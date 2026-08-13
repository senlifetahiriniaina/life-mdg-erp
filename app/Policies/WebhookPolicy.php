<?php
declare(strict_types=1);
namespace App\Policies;

use App\Models\User;
use App\Models\Webhook;

class WebhookPolicy
{
    public function view(User $user, Webhook $webhook): bool   { return $user->id === $webhook->user_id || $user->hasRole(['admin', 'super-admin']); }
    public function update(User $user, Webhook $webhook): bool { return $user->id === $webhook->user_id || $user->hasRole(['admin', 'super-admin']); }
    public function delete(User $user, Webhook $webhook): bool { return $user->id === $webhook->user_id || $user->hasRole(['admin', 'super-admin']); }
}
