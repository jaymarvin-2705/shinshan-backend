<?php

namespace App\Services;

use App\DTOs\TechnicianData;
use App\Models\Role;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Repositories\TechnicianRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TechnicianService
{
    public function __construct(
        protected TechnicianRepository $technicianRepository
    ) {}

    public function getAll(
        ?string $search = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        return $this->technicianRepository->getAll(
            $search,
            $perPage
        );
    }

    public function getById(
        int $technicianId
    ): TechnicianProfile {
        $technician = $this->technicianRepository->findById(
            $technicianId
        );

        if (!$technician) {
            throw ValidationException::withMessages([
                'technician' => [
                    'Technician not found.'
                ],
            ]);
        }

        return $technician;
    }

    public function create(
        TechnicianData $data
    ): TechnicianProfile {
        $existingUser = User::where(
            'email',
            $data->email
        )->first();

        if ($existingUser) {
            throw ValidationException::withMessages([
                'email' => [
                    'The email address is already registered.'
                ],
            ]);
        }

        $technicianRole = Role::where(
            'role_name',
            'Technician'
        )->firstOrFail();

        $user = User::create([
            'role_id' => $technicianRole->role_id,
            'fullname' => $data->fullname,
            'email' => $data->email,
            'phone' => $data->phone,
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        return $this->technicianRepository->create([
            'user_id' => $user->user_id,
            ...$data->toProfileArray(),
        ]);
    }

    public function update(
        int $technicianId,
        TechnicianData $data
    ): TechnicianProfile {
        $technician = $this->getById(
            $technicianId
        );

        $user = $technician->user;

        $existingUser = User::where(
            'email',
            $data->email
        )
            ->where(
                'user_id',
                '!=',
                $user->user_id
            )
            ->first();

        if ($existingUser) {
            throw ValidationException::withMessages([
                'email' => [
                    'The email address is already registered.'
                ],
            ]);
        }

        $user->update([
            'fullname' => $data->fullname,
            'email' => $data->email,
            'phone' => $data->phone,
        ]);

        return $this->technicianRepository->update(
            $technician,
            $data->toProfileArray()
        );
    }

    public function updateStatus(
        int $technicianId,
        string $status
    ): TechnicianProfile {
        $technician = $this->getById(
            $technicianId
        );

        $technician->user->update([
            'status' => $status,
        ]);

        return $technician->fresh('user');
    }

    public function updateAvailability(
        int $technicianId,
        string $availability
    ): TechnicianProfile {
        $technician = $this->getById(
            $technicianId
        );

        return $this->technicianRepository->update(
            $technician,
            [
                'availability' => $availability,
            ]
        );
    }
}