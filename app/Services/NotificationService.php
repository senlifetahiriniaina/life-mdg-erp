<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Write a real database notification (Laravel's default Notifiable shape — the
     * `notifications` table only has `id/type/notifiable/data/read_at`, no separate
     * `title`/`message` columns) and broadcast it in real time via Reverb.
     */
    public function sendToUser(User $user, string $title, string $message, array $data = []): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'broadcast',
            // Chantier 31: DatabaseNotification::$casts already declares
            // 'data' => 'array' (Laravel's own JSON cast) — passing a
            // pre-json_encode()'d string here double-encoded the payload on
            // every real write (the cast's outbound json_encode() wraps the
            // already-encoded string a second time), confirmed empirically
            // via tinker. Pass the raw array and let the cast serialize it.
            'data' => ['title' => $title, 'body' => $message, 'meta' => $data],
            'read_at' => null,
        ]);

        broadcast(new \App\Events\NotificationCreated($user->id, $title, $message, $data['type'] ?? 'info', $data));
    }

    public function sendTenantNotification(int $companyId, string $title, string $message, array $data = []): void
    {
        User::where('company_id', $companyId)->each(
            fn (User $user) => $this->sendToUser($user, $title, $message, $data)
        );
    }

    public function sendToRole(int $companyId, string $role, string $title, string $message, array $data = []): void
    {
        User::where('company_id', $companyId)
            ->role($role)
            ->each(fn (User $user) => $this->sendToUser($user, $title, $message, $data));
    }
}
