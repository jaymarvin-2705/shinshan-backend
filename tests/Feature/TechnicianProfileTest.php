<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TechnicianProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_technician_can_view_and_update_account_and_service_area_without_changing_availability(): void
    {
        [$technician, $profile] = $this->createTechnician();

        $this->actingAs($technician, 'sanctum')
            ->getJson('/api/v1/technician/profile')
            ->assertOk()
            ->assertJsonPath('data.fullname', 'Alex Morgan Lee')
            ->assertJsonPath('data.technician_profile.service_area', 'Soom')
            ->assertJsonPath('data.technician_profile.availability', 'busy');

        $this->actingAs($technician, 'sanctum')
            ->putJson('/api/v1/technician/profile', [
                'firstname' => 'Alex',
                'middlename' => null,
                'lastname' => 'Reyes',
                'email' => 'alex.reyes@example.test',
                'phone' => '09123456789',
                'service_area' => 'Poblacion',
                'availability' => 'available',
            ])
            ->assertOk()
            ->assertJsonPath('data.fullname', 'Alex Reyes')
            ->assertJsonPath('data.technician_profile.service_area', 'Poblacion')
            ->assertJsonPath('data.technician_profile.availability', 'busy');

        $this->assertSame('Poblacion', $profile->fresh()->service_area);
        $this->assertSame('busy', $profile->fresh()->availability);
        $this->assertDatabaseHas('users', [
            'user_id' => $technician->user_id,
            'fullname' => 'Alex Reyes',
            'email' => 'alex.reyes@example.test',
        ]);
    }

    public function test_technician_can_upload_a_profile_photo(): void
    {
        [$technician] = $this->createTechnician();
        Storage::fake('public');

        $response = $this->actingAs($technician, 'sanctum')
            ->post('/api/v1/technician/profile/photo', [
                'photo' => UploadedFile::fake()->image('profile.png'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['profile_photo_url']]);

        $this->assertIsString($response->json('data.profile_photo_url'));
        $this->assertNotEmpty($response->json('data.profile_photo_url'));
        $this->assertCount(1, Storage::disk('public')->allFiles('technician-profiles'));
    }

    public function test_non_technician_cannot_access_technician_profile_routes(): void
    {
        $role = Role::create(['role_name' => 'Client']);
        $client = User::create([
            'role_id' => $role->role_id,
            'fullname' => 'Client Account',
            'email' => 'client@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/technician/profile')
            ->assertForbidden();
    }

    private function createTechnician(): array
    {
        $role = Role::create(['role_name' => 'Technician']);
        $technician = User::create([
            'role_id' => $role->role_id,
            'firstname' => 'Alex',
            'middlename' => 'Morgan',
            'lastname' => 'Lee',
            'fullname' => 'Alex Morgan Lee',
            'email' => 'alex.lee@example.test',
            'phone' => '09111111111',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $profile = TechnicianProfile::create([
            'user_id' => $technician->user_id,
            'service_area' => 'Soom',
            'availability' => 'busy',
        ]);

        return [$technician, $profile];
    }
}
