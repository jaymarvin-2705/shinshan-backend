<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnnouncementFeedController extends Controller
{
    public function __construct(
        protected AnnouncementService $announcementService
    ) {}

    public function index(Request $request)
    {
        $audience = $this->audience($request);
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $notifications = $this->announcementService->feed(
            $request->user(),
            $audience,
            (int) ($validated['per_page'] ?? 20)
        );

        $notifications->getCollection()->transform(
            fn ($notification) => $this->formatNotification($notification, $audience)
        );

        return response()->json([
            'success' => true,
            'message' => 'Announcements retrieved successfully.',
            'data' => $notifications,
        ]);
    }

    public function unreadCount(Request $request)
    {
        $audience = $this->audience($request);

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $this->announcementService->unreadCount(
                    $request->user(),
                    $audience
                ),
            ],
        ]);
    }

    public function markRead(Request $request, int $announcementId)
    {
        $audience = $this->audience($request);
        $notification = $this->announcementService->markRead(
            $request->user(),
            $audience,
            $announcementId
        );

        return response()->json([
            'success' => true,
            'message' => 'Announcement marked as read.',
            'data' => $this->formatNotification($notification, $audience),
        ]);
    }

    public function markAllRead(Request $request)
    {
        $audience = $this->audience($request);
        $updated = $this->announcementService->markAllRead(
            $request->user(),
            $audience
        );

        return response()->json([
            'success' => true,
            'message' => 'Announcements marked as read.',
            'data' => ['updated_count' => $updated],
        ]);
    }

    public function attachment(Request $request, int $announcementId)
    {
        $audience = $this->audience($request);
        $path = $this->announcementService->recipientAttachment(
            $request->user(),
            $audience,
            $announcementId
        );

        return Storage::disk('local')->download($path);
    }

    private function formatNotification(Notification $notification, string $audience): array
    {
        $announcement = $notification->announcement;

        return [
            'notification_id' => $notification->notification_id,
            'announcement_id' => $announcement->announcement_id,
            'title' => $announcement->title,
            'message' => $announcement->content,
            'category' => $announcement->category,
            'target_audience' => $announcement->target_audience,
            'attachment_url' => $announcement->attachment_path
                ? route($audience === 'clients'
                    ? 'client.announcements.attachment'
                    : 'technician.announcements.attachment', [
                        'announcementId' => $announcement->announcement_id,
                    ])
                : null,
            'published_at' => $announcement->published_at,
            'is_read' => $notification->is_read,
            'read_at' => $notification->read_at,
        ];
    }

    private function audience(Request $request): string
    {
        return $request->user()->role->role_name === 'Client'
            ? 'clients'
            : 'technicians';
    }
}
