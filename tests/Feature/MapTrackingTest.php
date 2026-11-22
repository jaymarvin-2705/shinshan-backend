<?php

namespace Tests\Feature;

use App\Models\ClientProfile;
use App\Models\IssueCategory;
use App\Models\RepairRequest;
use App\Models\Role;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MapTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_submit_service_coordinates_for_a_request(): void
    {
        [$clientUser, $client] = $this->createClient();
        $category = IssueCategory::create(['category_name' => 'Internet']);

        $this->actingAs($clientUser, 'sanctum')
            ->postJson('/api/v1/client/repair-requests', [
                'category_id' => $category->category_id,
                'title' => 'No connection',
                'description' => 'The internet connection is unavailable.',
                'barangay' => 'Poblacion',
                'purok' => 'Purok 1',
                'service_latitude' => 9.86,
                'service_longitude' => 124.33,
            ])
            ->assertCreated()
            ->assertJsonPath('data.service_latitude', '9.86000000')
            ->assertJsonPath('data.service_longitude', '124.33000000');

        $this->assertDatabaseHas('repair_requests', [
            'client_id' => $client->client_id,
            'service_latitude' => 9.86,
            'service_longitude' => 124.33,
        ]);
    }

    public function test_admin_can_get_map_locations_and_technicians_can_update_their_own_location(): void
    {
        $adminRole = Role::create(['role_name' => 'Admin']);
        $admin = User::create([
            'role_id' => $adminRole->role_id,
            'fullname' => 'Map Admin',
            'email' => 'map-admin@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $technicianRole = Role::create(['role_name' => 'Technician']);
        $technicianUser = User::create([
            'role_id' => $technicianRole->role_id,
            'fullname' => 'Map Technician',
            'email' => 'map-technician@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $technician = TechnicianProfile::create([
            'user_id' => $technicianUser->user_id,
        ]);

        $this->actingAs($technicianUser, 'sanctum')
            ->patchJson('/api/v1/technician/location', [
                'latitude' => 9.87,
                'longitude' => 124.34,
            ])
            ->assertOk()
            ->assertJsonPath('data.technician_id', $technician->technician_id);

        $this->assertDatabaseHas('technician_profiles', [
            'technician_id' => $technician->technician_id,
            'current_latitude' => 9.87,
            'current_longitude' => 124.34,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/map-tracking')
            ->assertOk()
            ->assertJsonPath('data.technicians.0.technician_id', $technician->technician_id)
            ->assertJsonStructure([
                'data' => [
                    'technicians' => [[
                        'current_location_updated_at',
                    ]],
                ],
            ]);

        $this->assertNotNull(
            $technician->fresh()->current_location_updated_at
        );
    }

    public function test_client_cannot_access_admin_map_locations(): void
    {
        [$clientUser] = $this->createClient();

        $this->actingAs($clientUser, 'sanctum')
            ->getJson('/api/v1/admin/map-tracking')
            ->assertForbidden();
    }

    private function createClient(): array
    {
        $role = Role::create(['role_name' => 'Client']);
        $user = User::create([
            'role_id' => $role->role_id,
            'fullname' => 'Map Client',
            'email' => 'map-client@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $client = ClientProfile::create(['user_id' => $user->user_id]);

        return [$user, $client];
    }
}
