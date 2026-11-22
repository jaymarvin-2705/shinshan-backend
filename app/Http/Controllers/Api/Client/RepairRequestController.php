<?php

namespace App\Http\Controllers\Api\Client;

use App\DTOs\RepairRequestData;
use App\Http\Controllers\Controller;
use App\Services\RepairRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\RepairRequest;
use App\Services\OperationalNotificationService;
class RepairRequestController extends Controller
{
    public function __construct(
        protected RepairRequestService $repairRequestService,
        protected OperationalNotificationService $operationalNotifications
    ) {}

    public function index(Request $request)
    {
        $client = $request->user()->clientProfile;

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client profile not found.',
            ], 404);
        }

        $requests = $this->repairRequestService->getByClientId(
            $client->client_id,
            $request->query('status'),
            10
        );

        return response()->json([
            'success' => true,
            'message' => 'Service requests retrieved successfully.',
            'data' => $requests,
        ]);
    }

    public function show(
        Request $request,
        int $requestId
    ) {
        $client = $request->user()->clientProfile;

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client profile not found.',
            ], 404);
        }

        $repairRequest = $this->repairRequestService
            ->getClientRequest(
                $client->client_id,
                $requestId
            );

        return response()->json([
            'success' => true,
            'message' => 'Service request retrieved successfully.',
            'data' => $repairRequest,
        ]);
    }

    public function store(Request $request)
    {
        $client = $request->user()->clientProfile;

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client profile not found.',
            ], 404);
        }

        $validated = $request->validate([
            'category_id' => [
                'required',
                'integer',
                'exists:issue_categories,category_id',
            ],

            'title' => [
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'required',
                'string',
            ],
                        'barangay' => [
                'required',
                'string',
                Rule::in(RepairRequest::BARANGAYS),
            ],

            'purok' => [
                'required',
                'string',
                'regex:/^Purok \d{1,2}$/',
            ],

            'landmark' => [
                'nullable',
                'string',
                'max:120',
            ],

            'service_latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
                'required_with:service_longitude',
            ],

            'service_longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
                'required_with:service_latitude',
            ],

            'photo' => [
                'nullable',
                'string',
                'max:255',
            ],

            'priority' => [
                'nullable',
                Rule::in([
                    'low',
                    'medium',
                    'high',
                    'very_high',
                ]),
            ],
        ]);

        $validated['client_id'] = $client->client_id;
        $validated['status'] = 'pending';

        $repairRequest = $this->repairRequestService->create(
            RepairRequestData::fromArray($validated)
        );
        $this->operationalNotifications->newServiceRequest($repairRequest);

        return response()->json([
            'success' => true,
            'message' => 'Service request submitted successfully.',
            'data' => $repairRequest->load([
                'category'
            ]),
        ], 201);
    }
   
public function cancel(Request $request, RepairRequest $repairRequest)
{
    // Get the authenticated user's client profile.
    $client = $request->user()->clientProfile;

    if (!$client) {
        return response()->json([
            'success' => false,
            'message' => 'Client profile not found.',
        ], 404);
    }

    // Compare the request's client_id with the actual profile ID.
    if ((int) $repairRequest->client_id !== (int) $client->client_id) {
        return response()->json([
            'success' => false,
            'message' => 'You are not authorized to cancel this request.',
        ], 403);
    }

    // Only pending requests can be cancelled.
    if ($repairRequest->status !== 'pending') {
        return response()->json([
            'success' => false,
            'message' => 'Only pending repair requests can be cancelled.',
            'data' => [
                'status' => $repairRequest->status,
            ],
        ], 422);
    }

    $repairRequest->status = 'cancelled';
    $repairRequest->save();
    $this->operationalNotifications->requestStatusChanged(
        $repairRequest,
        'cancelled',
        "request:{$repairRequest->request_id}:cancelled:{$repairRequest->updated_at?->format('U.u')}"
    );

    return response()->json([
        'success' => true,
        'message' => 'Repair request cancelled successfully.',
        'data' => [
            'request_id' => $repairRequest->request_id,
            'status' => $repairRequest->status,
        ],
    ]);
}

}