<?php

namespace Tests\Feature;

use App\Models\ClientProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_view_and_update_account_and_location_details(): void
    {
        [$client, $profile] = $this->createClient();

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/profile')
            ->assertOk()
            ->assertJsonPath('data.fullname', 'Ana Maria Santos')
            ->assertJsonPath('data.client_profile.barangay', 'Soom');

        $this->actingAs($client, 'sanctum')
            ->putJson('/api/v1/client/profile', [
                'firstname' => 'Anita',
                'middlename' => null,
                'lastname' => 'Reyes',
                'email' => 'anita@example.test',
                'phone' => '09123456789',
                'birth_date' => '1994-03-21',
                'barangay' => 'Poblacion',
                'purok' => 'Purok 2',
                'address' => 'Near the community center',
            ])
            ->assertOk()
            ->assertJsonPath('data.fullname', 'Anita Reyes')
            ->assertJsonPath('data.email', 'anita@example.test')
            ->assertJsonPath('data.client_profile.address', 'Near the community center');

        $this->assertSame('Poblacion', $profile->fresh()->barangay);
        $this->assertSame('Purok 2', $profile->fresh()->purok);
        $this->assertDatabaseHas('users', [
            'user_id' => $client->user_id,
            'fullname' => 'Anita Reyes',
            'email' => 'anita@example.test',
        ]);
    }

    public function test_client_can_upload_a_profile_photo(): void
    {
        [$client] = $this->createClient();
        Storage::fake('public');

        $response = $this->actingAs($client, 'sanctum')
            ->post('/api/v1/client/profile/photo', [
                'photo' => UploadedFile::fake()->image('profile.png'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['profile_photo_url']]);

        $this->assertIsString($response->json('data.profile_photo_url'));

        $this->assertDatabaseHas('client_profiles', [
            'user_id' => $client->user_id,
        ]);
        $this->assertCount(1, Storage::disk('public')->allFiles('client-profiles'));
    }

    public function test_non_client_cannot_access_client_profile_routes(): void
    {
        $role = Role::create(['role_name' => 'Technician']);
        $technician = User::create([
            'role_id' => $role->role_id,
            'fullname' => 'Technician Account',
            'email' => 'technician@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this->actingAs($technician, 'sanctum')
            ->getJson('/api/v1/client/profile')
            ->assertForbidden();
    }

    private function createClient(): array
    {
        $role = Role::create(['role_name' => 'Client']);
        $client = User::create([
            'role_id' => $role->role_id,
            'firstname' => 'Ana',
            'middlename' => 'Maria',
            'lastname' => 'Santos',
            'fullname' => 'Ana Maria Santos',
            'email' => 'ana@example.test',
            'phone' => '09111111111',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $profile = ClientProfile::create([
            'user_id' => $client->user_id,
            'birth_date' => '1995-04-12',
            'barangay' => 'Soom',
            'purok' => 'Purok 3',
            'address' => 'Near the school',
        ]);

        return [$client, $profile];
    }
}
