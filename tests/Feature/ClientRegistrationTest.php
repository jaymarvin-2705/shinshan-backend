<?php

namespace Tests\Feature;

use App\Models\ClientProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['role_name' => 'Client']);
    }

    public function test_client_registration_creates_a_pending_user_and_profile(): void
    {
        $response = $this->postJson(
            '/api/v1/auth/register',
            $this->registrationData()
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.firstname', 'Ana')
            ->assertJsonPath('data.user.middlename', 'Maria')
            ->assertJsonPath('data.user.lastname', 'Santos')
            ->assertJsonPath('data.user.fullname', 'Ana Maria Santos')
            ->assertJsonPath('data.user.status', 'pending')
            ->assertJsonPath('data.client_profile.birth_date', '1995-04-12')
            ->assertJsonPath('data.client_profile.barangay', 'Soom')
            ->assertJsonPath('data.client_profile.purok', 'Purok 3')
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.user.password');

        $this->assertDatabaseHas('users', [
            'firstname' => 'Ana',
            'middlename' => 'Maria',
            'lastname' => 'Santos',
            'fullname' => 'Ana Maria Santos',
            'email' => 'ana@example.test',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('client_profiles', [
            'barangay' => 'Soom',
            'purok' => 'Purok 3',
        ]);

        $profile = ClientProfile::firstOrFail();
        $this->assertSame('1995-04-12', $profile->birth_date->toDateString());
    }

    public function test_registration_rejects_unknown_barangays(): void
    {
        $response = $this->postJson(
            '/api/v1/auth/register',
            [...$this->registrationData(), 'barangay' => 'Unknown']
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['barangay']);
    }

    public function test_registration_rejects_puroks_not_available_in_the_selected_barangay(): void
    {
        $this->postJson(
            '/api/v1/auth/register',
            [...$this->registrationData(), 'purok' => 'Purok 8']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['purok']);
    }

    public function test_registration_rejects_future_birth_dates(): void
    {
        $this->postJson(
            '/api/v1/auth/register',
            [...$this->registrationData(), 'birth_date' => '2999-01-01']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['birth_date']);
    }

    public function test_registration_rejects_duplicate_emails(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registrationData())
            ->assertCreated();

        $this->postJson('/api/v1/auth/register', $this->registrationData())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_allows_a_missing_middle_name(): void
    {
        $this->postJson(
            '/api/v1/auth/register',
            [...$this->registrationData(), 'middlename' => null]
        )
            ->assertCreated()
            ->assertJsonPath('data.user.fullname', 'Ana Santos')
            ->assertJsonPath('data.user.middlename', null);
    }

    public function test_location_endpoints_return_the_supported_barangays_and_puroks(): void
    {
        $this->getJson('/api/v1/locations/barangays')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['Soom']);

        $this->getJson('/api/v1/locations/puroks?barangay=Soom')
            ->assertOk()
            ->assertJsonPath('data.0', 'Purok 1')
            ->assertJsonPath('data.6', 'Purok 7');
    }

    public function test_admin_created_client_uses_registration_fields_and_remains_pending(): void
    {
        $adminRole = Role::create(['role_name' => 'Admin']);
        $admin = User::create([
            'role_id' => $adminRole->role_id,
            'fullname' => 'Admin User',
            'email' => 'admin@example.test',
            'password' => Hash::make('admin-password'),
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/clients', [
                ...$this->registrationData(),
                'password' => 'temporary-pass',
                'password_confirmation' => 'temporary-pass',
            ])
            ->assertCreated()
            ->assertJsonPath('data.user.firstname', 'Ana')
            ->assertJsonPath('data.user.middlename', 'Maria')
            ->assertJsonPath('data.user.lastname', 'Santos')
            ->assertJsonPath('data.user.fullname', 'Ana Maria Santos')
            ->assertJsonPath('data.user.status', 'pending')
            ->assertJsonPath('data.birth_date', '1995-04-12T00:00:00.000000Z')
            ->assertJsonMissingPath('data.user.password');

        $client = ClientProfile::with('user')->firstOrFail();
        $this->assertSame('Soom', $client->barangay);
        $this->assertSame('Purok 3', $client->purok);
        $this->assertTrue(Hash::check('temporary-pass', $client->user->password));
    }

    public function test_registration_still_accepts_the_legacy_fullname_payload(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'fullname' => 'Legacy Client',
            'email' => 'legacy@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.fullname', 'Legacy Client')
            ->assertJsonPath('data.user.status', 'pending');

        $this->assertDatabaseHas('users', [
            'fullname' => 'Legacy Client',
            'firstname' => null,
            'status' => 'pending',
        ]);
    }

    public function test_newly_registered_client_cannot_log_in_before_approval(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registrationData())
            ->assertCreated();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@example.test',
            'password' => 'password123',
        ])
            ->assertForbidden()
            ->assertJsonPath('status', 'pending');
    }

    private function registrationData(): array
    {
        return [
            'firstname' => 'Ana',
            'middlename' => 'Maria',
            'lastname' => 'Santos',
            'birth_date' => '1995-04-12',
            'barangay' => 'Soom',
            'purok' => 'Purok 3',
            'email' => 'ana@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }
}
