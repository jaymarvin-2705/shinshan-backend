<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperationalNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_alerts_mark_one_read_and_get_the_unread_count(): void
    {
        [$admin, $otherAdmin] = $this->createAdmins();
        $unread = $this->createAlert($admin, 'new_request', 'high', 'request:1:created');
        $this->createAlert($admin, 'status_changed', 'normal', 'request:2:status');
        $otherAlert = $this->createAlert(
            $otherAdmin,
            'new_request',
            'high',
            'request:1:created'
        );

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/notifications?read=unread&type=new_request&priority=high')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.notification_id', $unread->notification_id)
            ->assertJsonPath('unread_count', 2);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/notifications/{$unread->notification_id}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->assertFalse($otherAlert->fresh()->is_read);
    }

    public function test_mark_all_read_only_updates_the_current_admin_operational_alerts(): void
    {
        [$admin, $otherAdmin] = $this->createAdmins();
        $alert = $this->createAlert($admin, 'new_request', 'normal', 'request:10:created');
        $otherAlert = $this->createAlert(
            $otherAdmin,
            'new_request',
            'normal',
            'request:10:created'
        );
        $announcement = Notification::create([
            'user_id' => $admin->user_id,
            'title' => 'Announcement',
            'message' => 'Existing announcement notification.',
            'is_read' => false,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/v1/admin/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated_count', 1)
            ->assertJsonPath('unread_count', 0);

        $this->assertTrue($alert->fresh()->is_read);
        $this->assertFalse($otherAlert->fresh()->is_read);
        $this->assertFalse($announcement->fresh()->is_read);
    }

    public function test_client_can_read_and_mark_only_their_own_service_notifications(): void
    {
        $clientRole = Role::create(['role_name' => 'Client']);
        $technicianRole = Role::create(['role_name' => 'Technician']);
        $client = User::create([
            'role_id' => $clientRole->role_id,
            'fullname' => 'Notification Client',
            'email' => 'client-notifications@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $technician = User::create([
            'role_id' => $technicianRole->role_id,
            'fullname' => 'Notification Technician',
            'email' => 'tech-notifications@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $clientNotification = Notification::create([
            'user_id' => $client->user_id,
            'notification_type' => 'status_changed',
            'priority' => 'normal',
            'event_key' => 'request:21:accepted',
            'title' => 'Job accepted',
            'message' => 'The technician accepted the request.',
            'is_read' => false,
        ]);
        $technicianNotification = Notification::create([
            'user_id' => $technician->user_id,
            'notification_type' => 'technician_assigned',
            'priority' => 'normal',
            'event_key' => 'assignment:21:created',
            'title' => 'New service job assigned',
            'message' => 'A service request was assigned to you.',
            'is_read' => false,
        ]);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/notifications')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.notification_id', $clientNotification->notification_id)
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($client, 'sanctum')
            ->patchJson("/api/v1/client/notifications/{$technicianNotification->notification_id}/read")
            ->assertNotFound();

        $this->actingAs($client, 'sanctum')
            ->patchJson("/api/v1/client/notifications/{$clientNotification->notification_id}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertFalse($technicianNotification->fresh()->is_read);
    }

    private function createAdmins(): array
    {
        $role = Role::create(['role_name' => 'Admin']);

        $admin = User::create([
            'role_id' => $role->role_id,
            'fullname' => 'Notification Admin',
            'email' => 'notification-admin@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $otherAdmin = User::create([
            'role_id' => $role->role_id,
            'fullname' => 'Other Notification Admin',
            'email' => 'other-notification-admin@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        return [$admin, $otherAdmin];
    }

    private function createAlert(
        User $admin,
        string $type,
        string $priority,
        string $eventKey
    ): Notification {
        return Notification::create([
            'user_id' => $admin->user_id,
            'notification_type' => $type,
            'priority' => $priority,
            'event_key' => $eventKey,
            'title' => 'Operational alert',
            'message' => 'Test alert.',
            'is_read' => false,
        ]);
    }
}
