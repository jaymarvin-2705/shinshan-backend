<?php

namespace App\Repositories;

use App\Models\ClientProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientRepository
{
    public function getAll(
        ?string $search = null,
        int $perPage = 10,
        ?string $status = null
    ): LengthAwarePaginator {
        return ClientProfile::with('user.role')
            ->when($search, function ($query) use ($search) {
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where(function ($searchQuery) use ($search) {
                        $searchQuery
                            ->where('fullname', 'like', "%{$search}%")
                            ->orWhere('firstname', 'like', "%{$search}%")
                            ->orWhere('middlename', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                });
            })
            ->when($status, function ($query) use ($status) {
                $query->whereHas('user', function ($userQuery) use ($status) {
                    $userQuery->where('status', $status);
                });
            })
            ->latest('client_id')
            ->paginate($perPage);
    }

    public function findById(int $clientId): ?ClientProfile
    {
        return ClientProfile::with('user.role')
            ->where('client_id', $clientId)
            ->first();
    }

    public function create(array $data): ClientProfile
    {
        return ClientProfile::create($data);
    }

    public function update(
        ClientProfile $client,
        array $data
    ): ClientProfile {
        $client->update($data);

        return $client->fresh('user');
    }
}
