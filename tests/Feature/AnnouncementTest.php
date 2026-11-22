<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_publish_to_clients_only(): void
    {
        $admin = $this->userWithRole('Admin');
        $client = $this->userWithRole('Client');
        $technician = $this->userWithRole('Technician');

        $announcement = $this->createAnnouncement($admin, 'clients');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/announcements/{$announcement->announcement_id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('notifications', [
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $client->user_id,
            'is_read' => false,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $technician->user_id,
        ]);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/announcements')
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'Network maintenance');

        $this->actingAs($technician, 'sanctum')
            ->getJson('/api/v1/technician/announcements')
            ->assertOk()
            ->assertJsonCount(0, 'data.data');
    }

    public function test_all_audience_delivers_to_clients_and_technicians_but_not_admins(): void
    {
        $admin = $this->userWithRole('Admin');
        $client = $this->userWithRole('Client');
        $technician = $this->userWithRole('Technician');

        $announcement = $this->createAnnouncement($admin, 'all');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/announcements/{$announcement->announcement_id}/publish")
            ->assertOk();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $client->user_id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $technician->user_id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $admin->user_id,
        ]);
    }

    public function test_technicians_only_announcement_is_not_visible_to_clients(): void
    {
        $admin = $this->userWithRole('Admin');
        $client = $this->userWithRole('Client');
        $technician = $this->userWithRole('Technician');
        $announcement = $this->createAnnouncement($admin, 'technicians');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/announcements/{$announcement->announcement_id}/publish")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $technician->user_id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $client->user_id,
        ]);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/announcements')
            ->assertOk()
            ->assertJsonCount(0, 'data.data');
    }

    public function test_users_cannot_read_other_roles_announcements_or_marks(): void
    {
        $admin = $this->userWithRole('Admin');
        $client = $this->userWithRole('Client');
        $technician = $this->userWithRole('Technician');
        $announcement = $this->createAnnouncement($admin, 'clients');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/announcements/{$announcement->announcement_id}/publish")
            ->assertOk();

        $this->actingAs($technician, 'sanctum')
            ->getJson('/api/v1/client/announcements')
            ->assertForbidden();

        $this->actingAs($technician, 'sanctum')
            ->patchJson("/api/v1/technician/announcements/{$announcement->announcement_id}/read")
            ->assertNotFound();

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/announcements/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->actingAs($client, 'sanctum')
            ->patchJson("/api/v1/client/announcements/{$announcement->announcement_id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertDatabaseHas('notifications', [
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $client->user_id,
            'is_read' => true,
        ]);
    }

    public function test_repeated_publish_does_not_create_duplicate_recipient_notifications(): void
    {
        $admin = $this->userWithRole('Admin');
        $client = $this->userWithRole('Client');
        $announcement = $this->createAnnouncement($admin, 'clients');
        $url = "/api/v1/admin/announcements/{$announcement->announcement_id}/publish";

        $this->actingAs($admin, 'sanctum')->postJson($url)->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson($url)->assertOk();

        $this->assertSame(
            1,
            Notification::query()
                ->where('announcement_id', $announcement->announcement_id)
                ->where('user_id', $client->user_id)
                ->count()
        );
    }

    public function test_scheduled_announcement_is_published_when_due(): void
    {
        Carbon::setTestNow('2026-11-11 08:00:00');
        $admin = $this->userWithRole('Admin');
        $client = $this->userWithRole('Client');
        $announcement = Announcement::query()->create([
            'admin_id' => $admin->user_id,
            'title' => 'Scheduled notice',
            'content' => 'A planned update.',
            'category' => 'system_update',
            'target_audience' => 'clients',
            'status' => 'scheduled',
            'publish_at' => now()->addMinutes(2),
        ]);

        $this->artisan('announcements:publish-scheduled')->assertSuccessful();
        $this->assertSame('scheduled', $announcement->fresh()->status);
        $this->assertDatabaseMissing('notifications', [
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $client->user_id,
        ]);

        Carbon::setTestNow('2026-11-11 08:03:00');
        $this->artisan('announcements:publish-scheduled')->assertSuccessful();

        $this->assertSame('published', $announcement->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $client->user_id,
        ]);
    }

    public function test_non_admin_cannot_manage_announcements(): void
    {
        $client = $this->userWithRole('Client');

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/admin/announcements', [
                'title' => 'Not allowed',
                'content' => 'This should not be created.',
                'category' => 'general',
                'target_audience' => 'all',
            ])
            ->assertForbidden();
    }

    private function createAnnouncement(User $admin, string $audience): Announcement
    {
        return Announcement::query()->create([
            'admin_id' => $admin->user_id,
            'title' => 'Network maintenance',
            'content' => 'Service will be briefly unavailable.',
            'category' => 'maintenance',
            'target_audience' => $audience,
            'status' => 'draft',
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(['role_name' => $roleName]);

        return User::query()->create([
            'role_id' => $role->role_id,
            'fullname' => "{$roleName} Test",
            'email' => strtolower($roleName).'-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }
}
