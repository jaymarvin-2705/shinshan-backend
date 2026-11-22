<?php

namespace App\Http\Controllers\Api\Admin;

use App\DTOs\ClientData;
use App\Http\Controllers\Controller;
use App\Services\ClientService;
use App\Support\TrinidadLocationCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function __construct(
        protected ClientService $clientService
    ) {}

    
public function index(Request $request)
{
    $validated = $request->validate([
        'search' => ['nullable', 'string', 'max:100'],
        'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        'status' => [
            'nullable',
            'in:pending,active,inactive,rejected',
        ],
    ]);

    $clients = $this->clientService->getClients(
        search: $validated['search'] ?? null,
        perPage: (int) ($validated['per_page'] ?? 10),
        status: $validated['status'] ?? null
    );

    return response()->json([
        'success' => true,
        'message' => 'Clients retrieved successfully.',
        'data' => $clients,
    ]);
}


    public function show(int $clientId)
    {
        $client = $this->clientService->getClient(
            $clientId
        );

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Client retrieved successfully.',
            'data' => $client,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fullname' => [
                'required_without:firstname',
                'nullable',
                'string',
                'max:255',
            ],
            'firstname' => [
                'required_without:fullname',
                'nullable',
                'string',
                'max:80',
            ],
            'middlename' => ['nullable', 'string', 'max:80'],
            'lastname' => [
                'required_with:firstname',
                'nullable',
                'string',
                'max:80',
            ],
            'birth_date' => [
                'required_with:firstname',
                'nullable',
                'date_format:Y-m-d',
                'before:today',
            ],
            'barangay' => [
                'required_with:firstname',
                'nullable',
                'string',
                Rule::in(TrinidadLocationCatalog::barangays()),
            ],
            'purok' => [
                'required_with:firstname',
                'nullable',
                Rule::in(TrinidadLocationCatalog::puroks(
                    $request->input('barangay')
                )),
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],

            'address' => [
                'nullable',
                'string',
            ],
            'password' => [
                'required_with:firstname',
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $validated['firstname'] = isset($validated['firstname'])
            ? trim($validated['firstname'])
            : null;
        $validated['middlename'] = isset($validated['middlename'])
            ? trim($validated['middlename']) ?: null
            : null;
        $validated['lastname'] = isset($validated['lastname'])
            ? trim($validated['lastname'])
            : null;
        $validated['fullname'] = $validated['firstname'] !== null
            ? implode(' ', array_filter([
                $validated['firstname'],
                $validated['middlename'],
                $validated['lastname'],
            ], static fn (?string $name): bool => $name !== null && $name !== ''))
            : trim($validated['fullname']);
        $validated['email'] = strtolower(trim($validated['email']));

        $client = $this->clientService->createClient(
            ClientData::fromArray($validated),
            $validated['password'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Client created successfully.',
            'data' => $client,
        ], 201);
    }

    public function update(
        Request $request,
        int $clientId
    ) {
        $client = $this->clientService->getClient(
            $clientId
        );

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        $validated = $request->validate([
            'firstname' => ['nullable', 'string', 'max:80'],
            'middlename' => ['nullable', 'string', 'max:80'],
            'lastname' => ['nullable', 'string', 'max:80'],
            'fullname' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email,' .
                $client->user->user_id .
                ',user_id',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],

            'address' => [
                'nullable',
                'string',
            ],

            
        ]);

        $updatedClient = $this->clientService->updateClient(
            $client,
            ClientData::fromArray($validated)
        );

        return response()->json([
            'success' => true,
            'message' => 'Client updated successfully.',
            'data' => $updatedClient,
        ]);
    }

   
public function updateStatus(
    Request $request,
    int $clientId
) {
    $client = $this->clientService->getClient(
        $clientId
    );

    if (!$client) {
        return response()->json([
            'success' => false,
            'message' => 'Client not found.',
        ], 404);
    }

    $validated = $request->validate([
        'status' => [
            'required',
            'in:active,inactive,rejected',
        ],
    ]);

    $currentStatus = $client->user->status;
    $newStatus = $validated['status'];

    // Pending accounts can only be approved or rejected.
    if (
        $currentStatus === 'pending' &&
        !in_array($newStatus, ['active', 'rejected'], true)
    ) {
        return response()->json([
            'success' => false,
            'message' => 'Pending accounts can only be approved or rejected.',
        ], 422);
    }

    // Active accounts can be deactivated.
    if (
        $currentStatus === 'active' &&
        $newStatus !== 'inactive'
    ) {
        return response()->json([
            'success' => false,
            'message' => 'Active accounts can only be deactivated through this action.',
        ], 422);
    }

    // Inactive or rejected accounts can be reactivated.
    if (
        in_array($currentStatus, ['inactive', 'rejected'], true) &&
        $newStatus !== 'active'
    ) {
        return response()->json([
            'success' => false,
            'message' => 'Inactive or rejected accounts can only be reactivated through this action.',
        ], 422);
    }

    $updatedClient = $this->clientService->updateStatus(
        $client,
        $newStatus
    );

    $message = match ($newStatus) {
        'active' => 'Client account approved or activated successfully.',
        'inactive' => 'Client account deactivated successfully.',
        'rejected' => 'Client registration rejected successfully.',
    };

    return response()->json([
        'success' => true,
        'message' => $message,
        'data' => $updatedClient,
    ]);
}

}