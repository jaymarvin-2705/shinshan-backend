<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RepairRequest;
use App\Services\AssignmentService;
use App\Services\OperationalNotificationService;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function __construct(
        protected AssignmentService $assignmentService,
        protected OperationalNotificationService $operationalNotifications
    ) {}

    public function store(
        Request $request,
        int $requestId
    ) {
        $validated = $request->validate([
            'technician_id' => [
                'required',
                'integer',
                'exists:technician_profiles,technician_id',
            ],
        ]);

        $repairRequest = RepairRequest::find($requestId);

        if (!$repairRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Service request not found.',
            ], 404);
        }

        $assignment = $this->assignmentService->assign(
            repairRequest: $repairRequest,
            technicianId: $validated['technician_id'],
            assignedBy: $request->user()->user_id,
        );
        $this->operationalNotifications->technicianAssigned(
            $assignment->repairRequest,
            "assignment:{$assignment->assignment_id}:{$assignment->technician_id}:{$assignment->assigned_at?->format('U.u')}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Technician assigned successfully.',
            'data' => $assignment,
        ], 201);
    }

    public function autoAssign(
        Request $request,
        int $requestId
    ) {
        $repairRequest = RepairRequest::with([
            'client',
            'category',
        ])->find($requestId);

        if (!$repairRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Service request not found.',
            ], 404);
        }

        $assignment = $this->assignmentService->autoAssign(
            repairRequest: $repairRequest,
            assignedBy: $request->user()->user_id,
        );
        $this->operationalNotifications->technicianAssigned(
            $assignment->repairRequest,
            "assignment:{$assignment->assignment_id}:{$assignment->technician_id}:{$assignment->assigned_at?->format('U.u')}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Nearest technician assigned successfully.',
            'data' => $assignment,
        ], 201);
    }
}