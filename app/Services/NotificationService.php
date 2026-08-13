<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification as BaseNotification;

class NotificationService
{
    public function sendToUser(User $user, string $title, string $message, array $data = []): void
    {
        // Store in database
        $notification = $user->notifications()->create([
            'type' => 'broadcast',
            'title' => $title,
            'message' => $message,
            'data' => json_encode($data),
            'read_at' => null,
        ]);

        // Broadcast to user via Laravel Reverb WebSocket (configured in config/broadcasting.php)
        broadcast(new \App\Events\NotificationSent($notification, $user))->toOthers();
    }

    public function sendTenantNotification(int $tenantId, string $title, string $message, array $data = []): void
    {
        $users = User::whereTenantId($tenantId)->get();

        foreach ($users as $user) {
            $this->sendToUser($user, $title, $message, $data);
        }
    }

    public function sendToRole(int $tenantId, string $role, string $title, string $message, array $data = []): void
    {
        $users = User::whereTenantId($tenantId)
            ->role($role)
            ->get();

        foreach ($users as $user) {
            $this->sendToUser($user, $title, $message, $data);
        }
    }
}
