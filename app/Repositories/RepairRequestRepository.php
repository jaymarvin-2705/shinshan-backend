<?php

namespace App\Repositories;

use App\Models\RepairRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RepairRequestRepository
{
    public function getAll(
        ?string $search = null,
        ?string $status = null,
        ?string $priority = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        return RepairRequest::with([
            'client.user',
            'category',
            'assignment.technician.user',
        ])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere(
                            'description',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas('client.user', function ($query) use ($search) {
                            $query->where(
                                'fullname',
                                'like',
                                "%{$search}%"
                            );
                        })
                        ->orWhereHas('category', function ($query) use ($search) {
                            $query->where(
                                'category_name',
                                'like',
                                "%{$search}%"
                            );
                        });
                });
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($priority, function ($query) use ($priority) {
                $query->where('priority', $priority);
            })
            ->latest('request_id')
            ->paginate($perPage);
    }

    public function findById(int $requestId): ?RepairRequest
    {
        return RepairRequest::with([
            'client.user',
            'category',
            'assignment.technician.user',
        ])
            ->where('request_id', $requestId)
            ->first();
    }

    public function create(array $data): RepairRequest
    {
        return RepairRequest::create($data);
    }

    public function getByClientId(
        int $clientId,
        ?string $status = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        return RepairRequest::with($this->clientRelations())
            ->where('client_id', $clientId)
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest('request_id')
            ->paginate($perPage);
    }

    public function findClientRequest(
        int $clientId,
        int $requestId
    ): ?RepairRequest {
        return RepairRequest::with($this->clientRelations())
            ->where('request_id', $requestId)
            ->where('client_id', $clientId)
            ->first();
    }

    private function clientRelations(): array
    {
        return [
            'category',
            'feedback:feedback_id,request_id,client_id,technician_id,rating,comment,created_at',
            'assignment:assignment_id,request_id,technician_id,status',
            'assignment.technician:technician_id,user_id',
            'assignment.technician.user:user_id,fullname',
        ];
    }
}