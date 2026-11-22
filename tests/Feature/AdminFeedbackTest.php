<?php

namespace Tests\Feature;

use App\Models\ClientProfile;
use App\Models\Feedback;
use App\Models\IssueCategory;
use App\Models\JobAssignment;
use App\Models\RepairRequest;
use App\Models\Role;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_feedback_with_summary_and_related_details(): void
    {
        [$admin, $feedback] = $this->createFeedback();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/feedback')
            ->assertOk()
            ->assertJsonPath('data.data.0.feedback_id', $feedback->feedback_id)
            ->assertJsonPath('data.data.0.client.user.fullname', 'Feedback Client')
            ->assertJsonPath('data.data.0.technician.user.fullname', 'Feedback Technician')
            ->assertJsonPath('data.data.0.repair_request.title', 'Feedback request')
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.average_rating', 5)
            ->assertJsonPath('summary.rating_counts.5', 1)
            ->assertJsonPath('technicians.0.technician_id', $feedback->technician_id);
    }

    public function test_feedback_list_supports_rating_search_and_date_filters(): void
    {
        [$admin, $feedback] = $this->createFeedback();
        $feedback->update([
            'rating' => 3,
            'comment' => 'Could improve response time.',
            'created_at' => '2026-10-10 12:00:00',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/feedback?rating=3&search=improve&from_date=2026-10-01&to_date=2026-10-31')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.rating', 3);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/feedback?rating=5')
            ->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('summary.total', 1);
    }

    public function test_feedback_list_is_restricted_to_admins(): void
    {
        [, , $clientUser] = $this->createFeedback();

        $this->actingAs($clientUser, 'sanctum')
            ->getJson('/api/v1/admin/feedback')
            ->assertForbidden();
    }

    private function createFeedback(): array
    {
        $adminRole = Role::create(['role_name' => 'Admin']);
        $admin = User::create([
            'role_id' => $adminRole->role_id,
            'fullname' => 'Feedback Admin',
            'email' => 'feedback-admin@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $clientRole = Role::create(['role_name' => 'Client']);
        $clientUser = User::create([
            'role_id' => $clientRole->role_id,
            'fullname' => 'Feedback Client',
            'email' => 'feedback-client@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $client = ClientProfile::create(['user_id' => $clientUser->user_id]);

        $technicianRole = Role::create(['role_name' => 'Technician']);
        $technicianUser = User::create([
            'role_id' => $technicianRole->role_id,
            'fullname' => 'Feedback Technician',
            'email' => 'feedback-technician@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $technician = TechnicianProfile::create([
            'user_id' => $technicianUser->user_id,
        ]);

        $category = IssueCategory::create([
            'category_name' => 'Internet',
        ]);
        $repairRequest = RepairRequest::create([
            'client_id' => $client->client_id,
            'category_id' => $category->category_id,
            'title' => 'Feedback request',
            'description' => 'Connection repaired.',
            'priority' => 'medium',
            'status' => 'completed',
        ]);
        JobAssignment::create([
            'request_id' => $repairRequest->request_id,
            'technician_id' => $technician->technician_id,
            'status' => 'completed',
        ]);
        $feedback = Feedback::create([
            'request_id' => $repairRequest->request_id,
            'client_id' => $client->client_id,
            'technician_id' => $technician->technician_id,
            'rating' => 5,
            'comment' => 'Excellent work.',
        ]);

        return [$admin, $feedback, $clientUser];
    }
}
