<?php

namespace App\Services;

use App\DTOs\ClientData;
use App\Models\ClientProfile;
use App\Models\User;
use App\Repositories\ClientRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClientService
{
    public function __construct(
        protected ClientRepository $clientRepository
    ) {}

    
public function getClients(
    ?string $search = null,
    int $perPage = 10,
    ?string $status = null
): LengthAwarePaginator {
    return $this->clientRepository->getAll(
        $search,
        $perPage,
        $status
    );
}


    public function getClient(
        int $clientId
    ): ?ClientProfile {
        return $this->clientRepository->findById(
            $clientId
        );
    }

    public function createClient(
        ClientData $data,
        ?string $password = null
    ): ClientProfile {
        return DB::transaction(function () use ($data, $password) {
           $clientRole = \App\Models\Role::where(
    'role_name',
    'Client'
)->firstOrFail();
            $user = User::create([
                'role_id' => $clientRole->role_id,
                ...$data->toUserArray(),
                'password' => Hash::make($password ?? Str::random(64)),
                'status' => 'pending',
            ]);

            return $this->clientRepository->create([
                'user_id' => $user->user_id,
                ...$data->toProfileArray(),
            ])->load('user.role');
        });
    }

    public function updateClient(
        ClientProfile $client,
        ClientData $data
    ): ClientProfile {
        return DB::transaction(function () use (
            $client,
            $data
        ) {

            $client->user->update([
                'fullname' => $data->fullname,
                'email' => $data->email,
                'phone' => $data->phone,
            ]);

            return $this->clientRepository->update(
                $client,
                $data->toProfileArray()
            );
        });
    }

    public function updateStatus(
        ClientProfile $client,
        string $status
    ): ClientProfile {
        $client->user->update([
            'status' => $status,
        ]);

        return $client->fresh('user');
    }
}