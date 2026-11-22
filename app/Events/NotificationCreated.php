<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Notification $notification) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.'.$this->notification->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => [
                'notification_id' => $this->notification->notification_id,
                'notification_type' => $this->notification->notification_type,
                'priority' => $this->notification->priority,
                'related_request_id' => $this->notification->related_request_id,
                'title' => $this->notification->title,
                'message' => $this->notification->message,
                'is_read' => $this->notification->is_read,
                'read_at' => $this->notification->read_at?->toISOString(),
                'created_at' => $this->notification->created_at?->toISOString(),
            ],
        ];
    }
}
