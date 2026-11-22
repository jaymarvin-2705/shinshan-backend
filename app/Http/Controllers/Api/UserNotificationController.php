<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'read' => ['nullable', 'in:all,unread,read'],
        ]);

        $notifications = $this->userNotifications($request->user()->user_id)
            ->when(
                ($validated['read'] ?? 'all') === 'unread',
                fn ($query) => $query->where('is_read', false)
            )
            ->when(
                ($validated['read'] ?? 'all') === 'read',
                fn ($query) => $query->where('is_read', true)
            )
            ->latest('notification_id')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved successfully.',
            'data' => $notifications,
            'unread_count' => $this->allUserNotifications($request->user()->user_id)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $this->allUserNotifications($request->user()->user_id)
                    ->where('is_read', false)
                    ->count(),
            ],
        ]);
    }

    public function markRead(Request $request, int $notificationId)
    {
        $notification = $this->userNotifications($request->user()->user_id)
            ->where('notification_id', $notificationId)
            ->firstOrFail();

        if (! $notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => $notification->fresh(),
            'unread_count' => $this->allUserNotifications($request->user()->user_id)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    public function markAllRead(Request $request)
    {
        $updated = $this->userNotifications($request->user()->user_id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Notifications marked as read.',
            'data' => ['updated_count' => $updated],
            'unread_count' => $this->allUserNotifications($request->user()->user_id)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    private function userNotifications(int $userId)
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->whereNull('announcement_id')
            ->whereNotNull('notification_type');
    }

    private function allUserNotifications(int $userId)
    {
        return Notification::query()->where('user_id', $userId);
    }
}
