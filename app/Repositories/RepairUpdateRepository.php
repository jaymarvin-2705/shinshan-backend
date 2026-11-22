<?php

namespace App\Repositories;

use App\Models\RepairUpdate;

class RepairUpdateRepository
{
    public function create(array $data): RepairUpdate
    {
        return RepairUpdate::create($data);
    }

    public function getByAssignmentId(int $assignmentId)
    {
        return RepairUpdate::where(
            'assignment_id',
            $assignmentId
        )
            ->latest('update_id')
            ->get();
    }

    public function findById(int $updateId): ?RepairUpdate
    {
        return RepairUpdate::with([
            'assignment',
        ])
            ->where('update_id', $updateId)
            ->first();
    }
}