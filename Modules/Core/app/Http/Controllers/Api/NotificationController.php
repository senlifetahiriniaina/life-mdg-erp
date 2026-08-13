<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Core - Notifications
 *
 * In-app notifications stored in the `notifications` table (Laravel Database channel).
 * Real-time delivery via Reverb (WebSockets).
 */
class NotificationController extends Controller
{
    /**
     * List notifications for the authenticated user.
     *
     * @queryParam unread_only boolean Return only unread notifications. Example: 1
     * @queryParam per_page integer Results per page. Example: 20
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()
            ->notifications()
            ->when($request->boolean('unread_only'), fn ($q) => $q->whereNull('read_at'));

        return response()->json($query->paginate(min((int) ($request->per_page ?? 20), 100)));
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read.']);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->where('id', $id)->delete();

        return response()->json(null, 204);
    }
}
