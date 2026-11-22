<?php

namespace Tests\Feature;

use App\Models\ClientProfile;
use App\Models\IssueCategory;
use App\Models\JobAssignment;
use App\Models\RepairRequest;
use App\Models\Role;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientTechnicianFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_rate_the_technician_after_request_completion(): void
    {
        [$clientUser, $repairRequest] = $this->completedRequest();

        $this->actingAs($clientUser, 'sanctum')
            ->postJson(
                "/api/v1/client/repair-requests/{$repairRequest->request_id}/feedback",
                ['rating' => 5, 'comment' => 'Excellent service.']
            )
            ->assertCreated()
            ->assertJsonPath('data.request_id', $repairRequest->request_id)
            ->assertJsonPath('data.technician_id', $repairRequest->assignment->technician_id)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.comment', 'Excellent service.');

        $this->assertDatabaseHas('feedback', [
            'request_id' => $repairRequest->request_id,
            'client_id' => $repairRequest->client_id,
            'technician_id' => $repairRequest->assignment->technician_id,
            'rating' => 5,
            'comment' => 'Excellent service.',
        ]);
    }

    public function test_client_cannot_rate_a_request_before_completion(): void
    {
        [$clientUser, $repairRequest] = $this->completedRequest();
        $repairRequest->update(['status' => 'in_progress']);

        $this->actingAs($clientUser, 'sanctum')
            ->postJson(
                "/api/v1/client/repair-requests/{$repairRequest->request_id}/feedback",
                ['rating' => 4]
            )
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Only completed service requests can be rated.');

        $this->assertDatabaseCount('feedback', 0);
    }

    public function test_client_cannot_rate_another_clients_request(): void
    {
        [, $repairRequest] = $this->completedRequest();
        [$otherClientUser] = $this->createClient();

        $this->actingAs($otherClientUser, 'sanctum')
            ->postJson(
                "/api/v1/client/repair-requests/{$repairRequest->request_id}/feedback",
                ['rating' => 4]
            )
            ->assertNotFound();

        $this->assertDatabaseCount('feedback', 0);
    }

    public function test_a_completed_request_can_only_be_rated_once(): void
    {
        [$clientUser, $repairRequest] = $this->completedRequest();
        $url = "/api/v1/client/repair-requests/{$repairRequest->request_id}/feedback";

        $this->actingAs($clientUser, 'sanctum')
            ->postJson($url, ['rating' => 4])
            ->assertCreated();

        $this->actingAs($clientUser, 'sanctum')
            ->postJson($url, ['rating' => 2])
            ->assertStatus(409);

        $this->assertDatabaseCount('feedback', 1);
    }

    public function test_client_request_list_includes_rating_and_assigned_technician(): void
    {
        [$clientUser, $repairRequest, $technician] = $this->completedRequest();

        $this->actingAs($clientUser, 'sanctum')
            ->postJson(
                "/api/v1/client/repair-requests/{$repairRequest->request_id}/feedback",
                ['rating' => 5]
            )
            ->assertCreated();

        $this->actingAs($clientUser, 'sanctum')
            ->getJson('/api/v1/client/repair-requests')
            ->assertOk()
            ->assertJsonPath('data.data.0.assignment.technician.technician_id', $technician->technician_id)
            ->assertJsonPath('data.data.0.assignment.technician.user.fullname', 'Technician Test')
            ->assertJsonPath('data.data.0.feedback.rating', 5);
    }

    private function completedRequest(): array
    {
        [$clientUser, $client] = $this->createClient();

        $technicianRole = Role::create(['role_name' => 'Technician']);
        $technicianUser = User::create([
            'role_id' => $technicianRole->role_id,
            'fullname' => 'Technician Test',
            'email' => 'technician@example.test',
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
            'title' => 'No connection',
            'description' => 'The service is offline.',
            'priority' => 'medium',
            'status' => 'completed',
        ]);
        $repairRequest->assignment()->create([
            'technician_id' => $technician->technician_id,
            'status' => 'completed',
        ]);

        return [$clientUser, $repairRequest->load('assignment'), $technician];
    }

    private function createClient(): array
    {
        $role = Role::firstOrCreate(['role_name' => 'Client']);
        $user = User::create([
            'role_id' => $role->role_id,
            'fullname' => 'Client Test',
            'email' => 'client-' . uniqid() . '@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $client = ClientProfile::create(['user_id' => $user->user_id]);

        return [$user, $client];
    }
}
