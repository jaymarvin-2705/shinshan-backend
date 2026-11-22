<?php

namespace App\Services;

use App\DTOs\JobAssignmentData;
use App\Models\JobAssignment;
use App\Models\RepairRequest;
use App\Repositories\JobAssignmentRepository;
use App\Repositories\TechnicianRepository;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    public function __construct(
        protected JobAssignmentRepository $jobAssignmentRepository,
        protected TechnicianRepository $technicianRepository,
        protected DistanceService $distanceService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | MANUAL ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    public function assign(
        RepairRequest $repairRequest,
        int $technicianId,
        ?int $assignedBy = null
    ): JobAssignment {

        // A rejected assignment can be assigned to another technician.
        $existingAssignment =
            $this->jobAssignmentRepository->findByRequestId(
                $repairRequest->request_id
            );

        if ($existingAssignment && $existingAssignment->status !== 'rejected') {
            throw ValidationException::withMessages([
                'assignment' => [
                    'This service request is already assigned to a technician.',
                ],
            ]);
        }

        // Find technician
        $technician =
            $this->technicianRepository->findById(
                $technicianId
            );

        if (!$technician) {
            throw ValidationException::withMessages([
                'technician' => [
                    'Technician not found.',
                ],
            ]);
        }

        // Check availability
        if ($technician->availability !== 'available') {
            throw ValidationException::withMessages([
                'technician' => [
                    'Technician is not currently available.',
                ],
            ]);
        }

        if ($existingAssignment) {
            $assignment = $existingAssignment;
            $assignment->update([
                'technician_id' => $technician->technician_id,
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
                'status' => 'assigned',
                'remarks' => null,
            ]);
        } else {
            // Create assignment
            $data = new JobAssignmentData(
                requestId: $repairRequest->request_id,
                technicianId: $technician->technician_id,
                assignedBy: $assignedBy,
                status: 'assigned',
            );

            $assignment =
                $this->jobAssignmentRepository->create(
                    $data->toArray()
                );
        }

        // Update repair request
        $repairRequest->update([
            'status' => 'assigned',
        ]);

        // Technician is now busy
        $technician->update([
            'availability' => 'busy',
        ]);

        return $assignment->load([
            'repairRequest.category',
            'repairRequest.client.user',
            'technician.user',
            'assignedBy',
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | FIND NEAREST TECHNICIAN
    |--------------------------------------------------------------------------
    */

    public function findNearestTechnician(
        RepairRequest $repairRequest
    ) {

        // Get client profile
        $client = $repairRequest->client;

        if (!$client) {
            throw ValidationException::withMessages([
                'client' => [
                    'Client profile not found.',
                ],
            ]);
        }

        // Check client GPS
        if (
            $client->latitude === null ||
            $client->longitude === null
        ) {
            throw ValidationException::withMessages([
                'location' => [
                    'Client GPS location is not available.',
                ],
            ]);
        }

        // Get available technicians with GPS
        $technicians =
            $this->technicianRepository
                ->getAvailableTechnicians();

        if ($technicians->isEmpty()) {
            throw ValidationException::withMessages([
                'technician' => [
                    'No available technician with GPS location found.',
                ],
            ]);
        }

        // Prepare nearest technician variables
        $nearestTechnician = null;
        $shortestDistance = null;

        // Calculate distance for every available technician
        foreach ($technicians as $technician) {

            $distance =
                $this->distanceService->calculate(
                    (float) $client->latitude,
                    (float) $client->longitude,
                    (float) $technician->current_latitude,
                    (float) $technician->current_longitude
                );

            // First technician
            if ($shortestDistance === null) {
                $shortestDistance = $distance;
                $nearestTechnician = $technician;

                continue;
            }

            // Find closer technician
            if ($distance < $shortestDistance) {
                $shortestDistance = $distance;
                $nearestTechnician = $technician;
            }
        }

        return [
            'technician' => $nearestTechnician,
            'distance' => $shortestDistance,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | AUTOMATIC ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    public function autoAssign(
        RepairRequest $repairRequest,
        ?int $assignedBy = null
    ): JobAssignment {

        // Find nearest available technician
        $result = $this->findNearestTechnician(
            $repairRequest
        );

        $technician = $result['technician'];

        // Use the existing assignment logic
        return $this->assign(
            repairRequest: $repairRequest,
            technicianId: $technician->technician_id,
            assignedBy: $assignedBy
        );
    }
}