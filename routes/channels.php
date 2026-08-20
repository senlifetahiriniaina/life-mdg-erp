<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// App\Events\NotificationCreated broadcasts on new PrivateChannel("user.{id}") —
// a plain string channel, not Laravel's model-class-notation "App.Models.User.{id}"
// channel above. Both are real, distinct channel names; this one authorizes the
// literal one NotificationCreated (and Messaging's MessageSent-adjacent per-user
// events) actually use.
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Chantier 20 — Messaging: real membership check, not a raw tenant-id
// comparison — only the conversation's own participants may subscribe.
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return \Modules\Messaging\Models\ConversationParticipant::where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->exists();
});

// Tenant-isolated notification channels — company_id is the real tenant-boundary
// column in this app (users.tenant_id is a phantom, never-populated column
// documented extensively in CLAUDE.md; every one of these previously compared
// against it, making the whole check permanently false for every real user).
Broadcast::channel('notifications.{companyId}', function ($user, $companyId) {
    return (int) $user->company_id === (int) $companyId;
});

Broadcast::channel('dashboard.{companyId}', function ($user, $companyId) {
    return (int) $user->company_id === (int) $companyId;
});

Broadcast::channel('crm.contacts.{companyId}', function ($user, $companyId) {
    return (int) $user->company_id === (int) $companyId;
});

Broadcast::channel('inventory.products.{companyId}', function ($user, $companyId) {
    return (int) $user->company_id === (int) $companyId;
});

Broadcast::channel('hr.employees.{companyId}', function ($user, $companyId) {
    return (int) $user->company_id === (int) $companyId;
});

Broadcast::channel('accounting.invoices.{companyId}', function ($user, $companyId) {
    return (int) $user->company_id === (int) $companyId;
});

Broadcast::channel('queue.{companyId}', function ($user, $companyId) {
    return (int) $user->company_id === (int) $companyId;
});

Broadcast::channel('websocket.{companyId}', function ($user, $companyId) {
    return (int) $user->company_id === (int) $companyId;
});
