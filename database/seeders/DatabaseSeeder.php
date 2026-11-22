<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | ROLES
        |--------------------------------------------------------------------------
        */

        DB::table('roles')->insert([
            [
                'role_name' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'role_name' => 'Client',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'role_name' => 'Technician',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | ISSUE CATEGORIES
        |--------------------------------------------------------------------------
        */

        DB::table('issue_categories')->insert([
            [
                'category_name' => 'No Internet',
                'description' => 'Customer has no internet connection.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category_name' => 'Slow Internet',
                'description' => 'Internet connection is slower than expected.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category_name' => 'Intermittent Connection',
                'description' => 'Internet connection frequently disconnects.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category_name' => 'Router/Equipment Problem',
                'description' => 'Problem involving router or network equipment.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | USERS
        |--------------------------------------------------------------------------
        */

        DB::table('users')->insert([
            [
                'role_id' => 1,
                'firstname' => 'System',
                'middlename' => null,
                'lastname' => 'Administrator',
                'fullname' => 'System Administrator',
                'email' => 'admin@shinshan.test',
                'phone' => '09123456789',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'role_id' => 2,
                'firstname' => 'Test',
                'middlename' => 'Sample',
                'lastname' => 'Client',
                'fullname' => 'Test Sample Client',
                'email' => 'client@shinshan.test',
                'phone' => '09111111111',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'role_id' => 3,
                'firstname' => 'Test',
                'middlename' => null,
                'lastname' => 'Technician',
                'fullname' => 'Test Technician',
                'email' => 'technician@shinshan.test',
                'phone' => '09222222222',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | CLIENT PROFILES
        |--------------------------------------------------------------------------
        */

        DB::table('client_profiles')->insert([
            [
                'user_id' => 2,
                'birth_date' => '1995-04-12',
                'barangay' => 'Soom',
                'purok' => 'Purok 3',
                'address' => 'Test Client Address',
                'latitude' => null,
                'longitude' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | TECHNICIAN PROFILES
        |--------------------------------------------------------------------------
        */

        DB::table('technician_profiles')->insert([
            [
                'user_id' => 3,
                'service_area' => 'Trinidad, Bohol',
                'availability' => 'available',
                'current_latitude' => null,
                'current_longitude' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
