<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationalNotificationController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'read' => ['nullable', Rule::in(['all', 'unread', 'read'])],
            'type' => [
                'nullable',
                Rule::in([
                    'new_request',
                    'technician_assigned',
                    'status_changed',
                    'feedback_received',
                ]),
            ],
            'priority' => [
                'nullable',
                Rule::in(['normal', 'medium', 'high']),
            ],
        ]);

        $query = $this->operationalAlerts($request->user()->user_id)
            ->with([
                'repairRequest.client.user',
                'repairRequest.category',
                'repairRequest.assignment.technician.user',
            ])
            ->when(
                ($validated['read'] ?? 'all') === 'unread',
                fn ($query) => $query->where('is_read', false)
            )
            ->when(
                ($validated['read'] ?? 'all') === 'read',
                fn ($query) => $query->where('is_read', true)
            )
            ->when(
                $validated['type'] ?? null,
                fn ($query, $type) => $query->where('notification_type', $type)
            )
            ->when(
                $validated['priority'] ?? null,
                fn ($query, $priority) => $query->where('priority', $priority)
            )
            ->latest('notification_id');

        $notifications = $query->paginate((int) ($validated['per_page'] ?? 15));

        return response()->json([
            'success' => true,
            'message' => 'Operational notifications retrieved successfully.',
            'data' => $notifications,
            'unread_count' => $this->operationalAlerts($request->user()->user_id)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $this->operationalAlerts($request->user()->user_id)
                    ->where('is_read', false)
                    ->count(),
            ],
        ]);
    }

    public function markRead(Request $request, int $notificationId)
    {
        $notification = $this->operationalAlerts($request->user()->user_id)
            ->where('notification_id', $notificationId)
            ->firstOrFail();

        if (!$notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => $notification->fresh(),
            'unread_count' => $this->operationalAlerts($request->user()->user_id)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    public function markAllRead(Request $request)
    {
        $updated = $this->operationalAlerts($request->user()->user_id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Notifications marked as read.',
            'data' => ['updated_count' => $updated],
            'unread_count' => 0,
        ]);
    }

    private function operationalAlerts(int $userId)
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->whereNull('announcement_id')
            ->whereNotNull('notification_type');
    }
}
