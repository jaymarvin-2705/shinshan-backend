<?php

namespace App\DTOs;

class JobAssignmentData
{
    public function __construct(
        public int $requestId,
        public int $technicianId,
        public ?int $assignedBy,
        public string $status = 'assigned',
        public ?string $remarks = null,
    ) {}

    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'technician_id' => $this->technicianId,
            'assigned_by' => $this->assignedBy,
            'assigned_at' => now(),
            'status' => $this->status,
            'remarks' => $this->remarks,
        ];
    }
}