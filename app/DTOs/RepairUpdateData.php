<?php

namespace App\DTOs;

class RepairUpdateData
{
    public function __construct(
        public int $assignmentId,
        public string $status,
        public ?string $remarks = null,
        public ?string $image = null,
    ) {}

    public function toArray(): array
    {
        return [
            'assignment_id' => $this->assignmentId,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'image' => $this->image,
        ];
    }
}