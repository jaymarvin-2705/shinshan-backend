<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\RepairRequest;
use App\Models\Role;
use App\Models\User;

class OperationalNotificationService
{
    public function newServiceRequest(RepairRequest $repairRequest): void
    {
        $priority = in_array($repairRequest->priority, ['high', 'very_high'], true)
            ? 'high'
            : 'normal';

        $this->notifyAdmins(
            eventKey: "request:{$repairRequest->request_id}:created",
            type: 'new_request',
            priority: $priority,
            request: $repairRequest,
            title: 'New service request',
            message: sprintf(
                'Request #%d: %s was submitted and is waiting for review.',
                $repairRequest->request_id,
                $repairRequest->title
            )
        );
    }

    public function technicianAssigned(RepairRequest $repairRequest, string $eventKey): void
    {
        $repairRequest->loadMissing('assignment.technician.user');
        $assignment = $repairRequest->assignment;
        $technician = $assignment?->technician?->user?->fullname
            ?? 'A technician';

        $this->notifyAdmins(
            eventKey: $eventKey,
            type: 'technician_assigned',
            priority: 'normal',
            request: $repairRequest,
            title: 'Technician assigned',
            message: sprintf(
                '%s was assigned to request #%d: %s.',
                $technician,
                $repairRequest->request_id,
                $repairRequest->title
            )
        );

        $clientUserId = $repairRequest->client?->user_id;
        if ($clientUserId) {
            $this->notifyUser(
                userId: $clientUserId,
                eventKey: $eventKey,
                type: 'technician_assigned',
                priority: 'normal',
                request: $repairRequest,
                title: 'Technician assigned to your request',
                message: sprintf(
                    '%s was assigned to service request #%d: %s.',
                    $technician,
                    $repairRequest->request_id,
                    $repairRequest->title
                )
            );
        }

        $technicianUserId = $assignment?->technician?->user_id;
        if ($technicianUserId) {
            $this->notifyUser(
                userId: $technicianUserId,
                eventKey: $eventKey,
                type: 'technician_assigned',
                priority: 'normal',
                request: $repairRequest,
                title: 'New service job assigned',
                message: sprintf(
                    'You were assigned service request #%d: %s.',
                    $repairRequest->request_id,
                    $repairRequest->title
                )
            );
        }
    }

    public function requestStatusChanged(
        RepairRequest $repairRequest,
        string $status,
        string $eventKey
    ): void {
        $priority = in_array($status, ['cancelled'], true) ? 'medium' : 'normal';

        $this->notifyAdmins(
            eventKey: $eventKey,
            type: 'status_changed',
            priority: $priority,
            request: $repairRequest,
            title: 'Service request status updated',
            message: sprintf(
                'Request #%d: %s is now %s.',
                $repairRequest->request_id,
                $repairRequest->title,
                str_replace('_', ' ', $status)
            )
        );

        $clientStatuses = [
            'accepted',
            'rejected',
            'on_the_way',
            'arrived',
            'repair_started',
            'completed',
            'cancelled',
        ];

        if (!in_array($status, $clientStatuses, true)) {
            return;
        }

        $repairRequest->loadMissing('client');
        $clientUserId = $repairRequest->client?->user_id;
        if (!$clientUserId) {
            return;
        }

        [$title, $message] = $this->clientStatusMessage($repairRequest, $status);
        $this->notifyUser(
            userId: $clientUserId,
            eventKey: $eventKey,
            type: 'status_changed',
            priority: $priority,
            request: $repairRequest,
            title: $title,
            message: $message
        );
    }

    public function feedbackReceived(
        RepairRequest $repairRequest,
        int $rating,
        string $eventKey
    ): void {
        $this->notifyAdmins(
            eventKey: $eventKey,
            type: 'feedback_received',
            priority: $rating <= 2 ? 'high' : 'normal',
            request: $repairRequest,
            title: 'New customer feedback',
            message: sprintf(
                'A customer left a %d-star rating for request #%d: %s.',
                $rating,
                $repairRequest->request_id,
                $repairRequest->title
            )
        );
    }

    private function notifyAdmins(
        string $eventKey,
        string $type,
        string $priority,
        RepairRequest $request,
        string $title,
        string $message
    ): void {
        $adminRoleId = Role::query()
            ->where('role_name', 'Admin')
            ->value('role_id');

        if (!$adminRoleId) {
            return;
        }

        User::query()
            ->where('role_id', $adminRoleId)
            ->where('status', 'active')
            ->select('user_id')
            ->each(function (User $admin) use (
                $eventKey,
                $type,
                $priority,
                $request,
                $title,
                $message
            ) {
                $this->notifyUser(
                    userId: $admin->user_id,
                    eventKey: $eventKey,
                    type: $type,
                    priority: $priority,
                    request: $request,
                    title: $title,
                    message: $message
                );
            });
    }

    private function notifyUser(
        int $userId,
        string $eventKey,
        string $type,
        string $priority,
        RepairRequest $request,
        string $title,
        string $message
    ): void {
        $notification = Notification::query()->firstOrCreate(
            [
                'user_id' => $userId,
                'event_key' => $eventKey,
            ],
            [
                'notification_type' => $type,
                'priority' => $priority,
                'related_request_id' => $request->request_id,
                'title' => $title,
                'message' => $message,
                'is_read' => false,
            ]
        );

        if ($notification->wasRecentlyCreated) {
            NotificationCreated::dispatch($notification);
        }
    }

    private function clientStatusMessage(RepairRequest $request, string $status): array
    {
        $messages = [
            'accepted' => [
                'Job accepted',
                'The technician accepted service request #%d: %s.',
            ],
            'rejected' => [
                'Technician unavailable',
                'The assigned technician could not accept request #%d: %s. We will arrange the next step.',
            ],
            'on_the_way' => [
                'Technician on the way',
                'The technician is on the way for request #%d: %s.',
            ],
            'arrived' => [
                'Technician arrived',
                'The technician has arrived for request #%d: %s.',
            ],
            'repair_started' => [
                'Repair started',
                'Work has started on request #%d: %s.',
            ],
            'completed' => [
                'Service completed',
                'Request #%d: %s has been completed. You can now leave feedback.',
            ],
            'cancelled' => [
                'Service request cancelled',
                'Request #%d: %s has been cancelled.',
            ],
        ];

        [$title, $message] = $messages[$status];

        return [
            $title,
            sprintf($message, $request->request_id, $request->title),
        ];
    }
}
