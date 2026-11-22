<?php

namespace App\Services;

use App\DTOs\RepairRequestData;
use App\Models\RepairRequest;
use App\Repositories\RepairRequestRepository;
use Illuminate\Validation\ValidationException;

class RepairRequestService
{
    public function __construct(
        protected RepairRequestRepository $repairRequestRepository
    ) {}

    public function getAll(
        ?string $search = null,
        ?string $status = null,
        ?string $priority = null,
        int $perPage = 10
    ) {
        return $this->repairRequestRepository->getAll(
            search: $search,
            status: $status,
            priority: $priority,
            perPage: $perPage
        );
    }

    public function getById(int $requestId): RepairRequest
    {
        $repairRequest = $this->repairRequestRepository
            ->findById($requestId);

        if (!$repairRequest) {
            throw ValidationException::withMessages([
                'request' => [
                    'Service request not found.'
                ],
            ]);
        }

        return $repairRequest;
    }

    public function create(
        RepairRequestData $data
    ): RepairRequest {
        return $this->repairRequestRepository
            ->create($data->toArray());
    }

    public function getByClientId(
        int $clientId,
        ?string $status = null,
        int $perPage = 10
    ) {
        return $this->repairRequestRepository->getByClientId(
            clientId: $clientId,
            status: $status,
            perPage: $perPage
        );
    }

    public function getClientRequest(
        int $clientId,
        int $requestId
    ): RepairRequest {
        $repairRequest = $this->repairRequestRepository
            ->findClientRequest(
                clientId: $clientId,
                requestId: $requestId
            );

        if (!$repairRequest) {
            throw ValidationException::withMessages([
                'request' => [
                    'Service request not found.'
                ],
            ]);
        }

        return $repairRequest;
    }
}