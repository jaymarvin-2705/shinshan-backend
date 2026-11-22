<?php

namespace Tests\Feature;

use App\Models\ClientProfile;
use App\Models\Feedback;
use App\Models\IssueCategory;
use App\Models\JobAssignment;
use App\Models\RepairRequest;
use App\Models\RepairUpdate;
use App\Models\Role;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_filtered_report_metrics_and_breakdowns(): void
    {
        [$admin, $repairRequest, $technician] = $this->createCompletedRequest();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/reports?start_date=2026-11-01&end_date=2026-11-30')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.completed', 1)
            ->assertJsonPath('data.summary.open', 0)
            ->assertJsonPath('data.summary.completion_rate', 100)
            ->assertJsonPath('data.summary.average_completion_minutes', 1560)
            ->assertJsonPath('data.trend.0.period', '2026-11')
            ->assertJsonPath('data.trend.0.requests', 1)
            ->assertJsonPath('data.trend.0.completed', 1)
            ->assertJsonPath('data.technician_performance.0.technician_id', $technician->technician_id)
            ->assertJsonPath('data.requests.data.0.request_id', $repairRequest->request_id);
    }

    public function test_non_admin_cannot_access_reports(): void
    {
        [, , , $clientUser] = $this->createCompletedRequest();

        $this->actingAs($clientUser, 'sanctum')
            ->getJson('/api/v1/admin/reports?start_date=2026-11-01&end_date=2026-11-30')
            ->assertForbidden();
    }

    private function createCompletedRequest(): array
    {
        $adminRole = Role::create(['role_name' => 'Admin']);
        $admin = User::create([
            'role_id' => $adminRole->role_id,
            'fullname' => 'Report Admin',
            'email' => 'report-admin@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $clientRole = Role::create(['role_name' => 'Client']);
        $clientUser = User::create([
            'role_id' => $clientRole->role_id,
            'fullname' => 'Report Client',
            'email' => 'report-client@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $client = ClientProfile::create(['user_id' => $clientUser->user_id]);

        $technicianRole = Role::create(['role_name' => 'Technician']);
        $technicianUser = User::create([
            'role_id' => $technicianRole->role_id,
            'fullname' => 'Report Technician',
            'email' => 'report-technician@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $technician = TechnicianProfile::create([
            'user_id' => $technicianUser->user_id,
        ]);

        $category = IssueCategory::create(['category_name' => 'Internet']);
        $repairRequest = RepairRequest::create([
            'client_id' => $client->client_id,
            'category_id' => $category->category_id,
            'title' => 'Report request',
            'description' => 'Connection repaired.',
            'priority' => 'medium',
            'status' => 'completed',
            'barangay' => 'Poblacion',
        ]);
        $repairRequest->update([
            'created_at' => '2026-11-05 10:00:00',
        ]);
        $assignment = JobAssignment::create([
            'request_id' => $repairRequest->request_id,
            'technician_id' => $technician->technician_id,
            'status' => 'completed',
            'assigned_at' => '2026-11-05 11:00:00',
        ]);
        $repairUpdate = RepairUpdate::create([
            'assignment_id' => $assignment->assignment_id,
            'status' => 'completed',
            'remarks' => 'Completed.',
        ]);
        $repairUpdate->update(['created_at' => '2026-11-06 12:00:00']);
        Feedback::create([
            'request_id' => $repairRequest->request_id,
            'client_id' => $client->client_id,
            'technician_id' => $technician->technician_id,
            'rating' => 5,
        ]);

        return [$admin, $repairRequest, $technician, $clientUser];
    }
}
