<?php

namespace App\Repositories;

use App\Models\TechnicianProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
class TechnicianRepository
{
    public function getAll(
        ?string $search = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        return TechnicianProfile::with('user')
            ->when($search, function ($query) use ($search) {
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery
                        ->where('fullname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })
                ->orWhere(
                    'service_area',
                    'like',
                    "%{$search}%"
                );
            })
            ->latest('technician_id')
            ->paginate($perPage);
    }

    public function findById(
        int $technicianId
    ): ?TechnicianProfile {
        return TechnicianProfile::with('user')
            ->where(
                'technician_id',
                $technicianId
            )
            ->first();
    }

    public function create(
        array $data
    ): TechnicianProfile {
        return TechnicianProfile::create($data);
    }

    public function update(
        TechnicianProfile $technician,
        array $data
    ): TechnicianProfile {
        $technician->update($data);

        return $technician->fresh('user');
    }
    public function getAvailableTechnicians()
{
    return TechnicianProfile::with([
        'user',
    ])
        ->where('availability', 'available')
        ->whereNotNull('current_latitude')
        ->whereNotNull('current_longitude')
        ->get();
}
}