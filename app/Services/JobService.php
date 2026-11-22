<?php

namespace App\Services;

use App\DTOs\RepairUpdateData;
use App\Models\JobAssignment;
use App\Repositories\JobAssignmentRepository;
use App\Repositories\RepairUpdateRepository;
use Illuminate\Validation\ValidationException;

class JobService
{
    public function __construct(
        protected JobAssignmentRepository $jobAssignmentRepository,
        protected RepairUpdateRepository $repairUpdateRepository
    ) {}

    public function getTechnicianJobs(
        int $technicianId,
        int $perPage = 10
    ) {
        return $this->jobAssignmentRepository
            ->getByTechnicianId(
                technicianId: $technicianId,
                perPage: $perPage
            );
    }

    public function getJobForTechnician(
        int $technicianId,
        int $assignmentId
    ): JobAssignment
    {
        $assignment = JobAssignment::with([
            'repairRequest.category',
            'repairRequest.client.user',
            'technician.user',
            'schedule',
            'repairUpdates',
        ])
            ->where(
                'assignment_id',
                $assignmentId
            )
            ->where(
                'technician_id',
                $technicianId
            )
            ->first();

        if (!$assignment) {
            throw ValidationException::withMessages([
                'assignment' => [
                    'Job not found.'
                ],
            ]);
        }

        return $assignment;
    }

   public function updateStatus(
    JobAssignment $assignment,
    string $status,
    ?string $remarks = null,
    ?string $image = null
): JobAssignment {

    $allowedStatuses = [
        'rejected',
        'accepted',
        'on_the_way',
        'arrived',
        'repair_started',
        'completed',
        'cancelled',
    ];

    if (!in_array($status, $allowedStatuses, true)) {
        throw ValidationException::withMessages([
            'status' => [
                'Invalid repair status.'
            ],
        ]);
    }

    $latestUpdate = $assignment->repairUpdates
        ->sortByDesc('update_id')
        ->first();

    if ($latestUpdate?->status === $status) {
        throw ValidationException::withMessages([
            'status' => [
                'This job status has already been recorded.',
            ],
        ]);
    }

    if ($status === 'rejected' && $assignment->status !== 'assigned') {
        throw ValidationException::withMessages([
            'status' => [
                'Only assigned jobs can be rejected.',
            ],
        ]);
    }

    if ($status === 'completed') {

        if (!$remarks) {
            throw ValidationException::withMessages([
                'remarks' => [
                    'Completion remarks are required when completing a job.'
                ],
            ]);
        }
    }

    if ($status !== 'rejected') {
        $update = new RepairUpdateData(
            assignmentId: $assignment->assignment_id,
            status: $status,
            remarks: $remarks,
            image: $image,
        );

        $this->repairUpdateRepository->create(
            $update->toArray()
        );
    }

    $assignmentStatus = match ($status) {

        'rejected' => 'rejected',
        'accepted' => 'accepted',
        'on_the_way' => 'on_the_way',
        'arrived',
        'repair_started' => 'in_progress',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
        default => $assignment->status,
    };


    $assignment->update([
        'status' => $assignmentStatus,
        'remarks' => $status === 'rejected'
            ? ($remarks ?? 'Rejected by technician.')
            : $remarks,
    ]);

    $assignment->repairRequest()->update([
        'status' => $this->mapRequestStatus($status),
    ]);

    if (
        $status === 'completed' ||
        $status === 'cancelled' ||
        $status === 'rejected'
    ) {
        $assignment->technician()->update([
            'availability' => 'available',
        ]);
    }

    return $assignment->load([
        'repairRequest.category',
        'repairRequest.client.user',
        'technician.user',
        'schedule',
        'repairUpdates',
    ]);
}
    private function mapRequestStatus(
        string $status
    ): string {
        return match ($status) {
            'rejected' => 'pending',
            'accepted' => 'assigned',
            'on_the_way' => 'on_the_way',
            'arrived',
            'repair_started' => 'in_progress',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => 'assigned',
        };
    }

    public function getAllJobs(
    ?string $search = null,
    ?string $status = null,
    int $perPage = 10
) {
    return $this->jobAssignmentRepository->getAll(
        search: $search,
        status: $status,
        perPage: $perPage
    );
}
public function getJobById(
    int $assignmentId
): JobAssignment {

    $job = $this->jobAssignmentRepository
        ->findById($assignmentId);

    if (!$job) {
        throw ValidationException::withMessages([
            'assignment' => [
                'Job not found.'
            ],
        ]);
    }

    return $job;
}
}