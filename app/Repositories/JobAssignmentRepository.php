<?php

namespace App\Repositories;

use App\Models\JobAssignment;

class JobAssignmentRepository
{
    public function create(array $data): JobAssignment
    {
        return JobAssignment::create($data);
    }

    public function findById(int $assignmentId): ?JobAssignment
    {
        return JobAssignment::with([
            'repairRequest.category',
            'repairRequest.client.user',
            'repairRequest.feedback',
            'technician.user',
            'assignedBy',
            'schedule',
            'repairUpdates',
        ])
            ->where('assignment_id', $assignmentId)
            ->first();
    }

    public function findByRequestId(int $requestId): ?JobAssignment
    {
        return JobAssignment::with([
            'repairRequest',
            'technician.user',
            'assignedBy',
            'schedule',
        ])
            ->where('request_id', $requestId)
            ->first();
    }

    public function getByTechnicianId(
        int $technicianId,
        int $perPage = 10
    ) {
        return JobAssignment::with([
            'repairRequest.category',
            'repairRequest.client.user',
            'schedule',
        ])
            ->where('technician_id', $technicianId)
            ->latest('assignment_id')
            ->paginate($perPage);
    }
    public function getAll(
    ?string $search = null,
    ?string $status = null,
    int $perPage = 10
) {
    return JobAssignment::with([
        'repairRequest.category',
        'repairRequest.client.user',
        'repairRequest.feedback',
        'technician.user',
        'assignedBy',
        'schedule',
        'repairUpdates',
    ])
        ->when($search, function ($query) use ($search) {
            $query->where(function ($query) use ($search) {

                $query->whereHas(
                    'repairRequest',
                    function ($query) use ($search) {
                        $query
                            ->where(
                                'title',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%"
                            );
                    }
                );

                $query->orWhereHas(
                    'repairRequest.client.user',
                    function ($query) use ($search) {
                        $query->where(
                            'fullname',
                            'like',
                            "%{$search}%"
                        );
                    }
                );

                $query->orWhereHas(
                    'technician.user',
                    function ($query) use ($search) {
                        $query->where(
                            'fullname',
                            'like',
                            "%{$search}%"
                        );
                    }
                );

            });
        })
        ->when($status, function ($query) use ($status) {
            $query->where('status', $status);
        })
        ->latest('assignment_id')
        ->paginate($perPage);
}
}