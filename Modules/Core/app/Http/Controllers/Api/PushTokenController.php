<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Core - Push Notifications
 *
 * Register and manage Expo push tokens for mobile push notifications.
 */
class PushTokenController extends Controller
{
    /**
     * Register or refresh a push token.
     *
     * @bodyParam token string required The Expo push token (ExponentPushToken[xxx]). Example: ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]
     * @bodyParam platform string The platform (expo|apns|fcm). Defaults to expo. Example: expo
     * @bodyParam device_name string Optional human-readable device name. Example: iPhone 15 Pro
     *
     * @response 200 {"message":"Token registered"}
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'in:expo,apns,fcm'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        PushToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? 'expo',
                'device_name' => $validated['device_name'] ?? null,
                'last_used_at' => now(),
            ]
        );

        return response()->json(['message' => 'Token registered']);
    }

    /**
     * Remove a push token (e.g. on logout).
     *
     * @bodyParam token string required The token to deregister.
     *
     * @response 204 {}
     */
    public function deregister(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        PushToken::where('user_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->delete();

        return response()->json(null, 204);
    }
}
