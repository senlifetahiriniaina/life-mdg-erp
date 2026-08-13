<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Channels;

use App\Models\PushToken;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushChannel
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toExpoPush')) {
            return;
        }

        $tokens = PushToken::where('user_id', $notifiable->getKey())
            ->where('platform', 'expo')
            ->pluck('token')
            ->all();

        if (empty($tokens)) {
            return;
        }

        $message = $notification->toExpoPush($notifiable);

        $messages = array_map(fn (string $token) => array_merge(
            ['to' => $token, 'sound' => 'default'],
            $message
        ), $tokens);

        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->post(self::EXPO_PUSH_URL, $messages);

            if ($response->failed()) {
                Log::warning('Expo push failed', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            Log::error('Expo push error', ['error' => $e->getMessage()]);
        }
    }
}
