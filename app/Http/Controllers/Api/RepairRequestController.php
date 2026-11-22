<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RepairRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairRequestController extends Controller
{
    public function __construct(
        protected RepairRequestService $repairRequestService
    ) {}

    public function index(Request $request)
    {
        $requests = $this->repairRequestService->getAll(
            search: $request->query('search'),
            status: $request->query('status'),
            priority: $request->query('priority'),
            perPage: (int) $request->query('per_page', 10)
        );

        return response()->json([
            'success' => true,
            'message' => 'Service requests retrieved successfully.',
            'data' => $requests,
        ]);
    }

    public function show(int $requestId)
    {
        $repairRequest = $this->repairRequestService
            ->getById($requestId);

        return response()->json([
            'success' => true,
            'message' => 'Service request retrieved successfully.',
            'data' => $repairRequest,
        ]);
    }

    public function updatePriority(Request $request, int $requestId)
    {
        $validated = $request->validate([
            'priority' => [
                'required',
                Rule::in(['medium', 'high', 'very_high']),
            ],
        ]);

        $repairRequest = $this->repairRequestService->getById($requestId);
        $repairRequest->update(['priority' => $validated['priority']]);

        return response()->json([
            'success' => true,
            'message' => 'Service request priority updated successfully.',
            'data' => $repairRequest->fresh()->load([
                'client.user',
                'category',
                'assignment.technician.user',
            ]),
        ]);
    }
}