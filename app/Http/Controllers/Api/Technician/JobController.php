<?php

namespace App\Http\Controllers\Api\Technician;

use App\Http\Controllers\Controller;
use App\Models\JobAssignment;
use App\Services\JobService;
use App\Services\OperationalNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobController extends Controller
{
    public function __construct(
        protected JobService $jobService,
        protected OperationalNotificationService $operationalNotifications
    ) {}

    public function index(Request $request)
    {
        $technician =
            $request->user()->technicianProfile;

        if (!$technician) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Technician profile not found.',
            ], 404);
        }

        $jobs =
            $this->jobService->getTechnicianJobs(
                technicianId:
                    $technician->technician_id,
                perPage: 10
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Technician jobs retrieved successfully.',
            'data' => $jobs,
        ]);
    }

    public function show(
        Request $request,
        int $assignmentId
    ) {
        $technician =
            $request->user()->technicianProfile;

        if (!$technician) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Technician profile not found.',
            ], 404);
        }

        $job =
            $this->jobService->getJobForTechnician(
                technicianId:
                    $technician->technician_id,
                assignmentId: $assignmentId
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Job retrieved successfully.',
            'data' => $job,
        ]);
    }

    public function updateStatus(
        Request $request,
        int $assignmentId
    ) {
        $technician =
            $request->user()->technicianProfile;

        if (!$technician) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Technician profile not found.',
            ], 404);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'rejected',
                    'accepted',
                    'on_the_way',
                    'arrived',
                    'repair_started',
                    'completed',
                    'cancelled',
                ]),
            ],
            'remarks' => [
                'nullable',
                'string',
            ],
            'image' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $job =
            $this->jobService->getJobForTechnician(
                technicianId:
                    $technician->technician_id,
                assignmentId: $assignmentId
            );

        $updatedJob =
            $this->jobService->updateStatus(
                assignment: $job,
                status: $validated['status'],
                remarks:
                    $validated['remarks'] ?? null,
                image:
                    $validated['image'] ?? null,
            );
        $latestUpdate = $updatedJob->repairUpdates
            ->sortByDesc('update_id')
            ->first();

        if ($validated['status'] === 'rejected' && $updatedJob->repairRequest) {
            $this->operationalNotifications->requestStatusChanged(
                $updatedJob->repairRequest,
                'rejected',
                "assignment:{$updatedJob->assignment_id}:{$updatedJob->technician_id}:rejected:{$updatedJob->updated_at?->format('U.u')}"
            );
        } elseif ($latestUpdate && $updatedJob->repairRequest) {
            $this->operationalNotifications->requestStatusChanged(
                $updatedJob->repairRequest,
                $validated['status'],
                "repair-update:{$latestUpdate->update_id}"
            );
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Job status updated successfully.',
            'data' => $updatedJob,
        ]);
    }

    public function updateLocation(Request $request)
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $technician = $request->user()->technicianProfile;

        if (!$technician) {
            return response()->json([
                'success' => false,
                'message' => 'Technician profile not found.',
            ], 404);
        }

        $technician->update([
            'current_latitude' => $validated['latitude'],
            'current_longitude' => $validated['longitude'],
            'current_location_updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Technician location updated successfully.',
            'data' => [
                'technician_id' => $technician->technician_id,
                'current_latitude' => $technician->current_latitude,
                'current_longitude' => $technician->current_longitude,
                'updated_at' => $technician->current_location_updated_at,
            ],
        ]);
    }
}