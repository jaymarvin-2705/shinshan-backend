<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\RepairRequest;
use App\Services\OperationalNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeedbackController extends Controller
{
    public function __construct(
        protected OperationalNotificationService $operationalNotifications
    ) {}

    public function store(Request $request, int $requestId)
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $client = $request->user()->clientProfile;

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client profile not found.',
            ], 404);
        }

        $feedback = DB::transaction(function () use (
            $client,
            $requestId,
            $validated
        ) {
            $repairRequest = RepairRequest::query()
                ->where('request_id', $requestId)
                ->where('client_id', $client->client_id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $repairRequest->status === 'completed',
                422,
                'Only completed service requests can be rated.'
            );

            $assignment = $repairRequest->assignment()
                ->with('technician')
                ->first();

            abort_unless(
                $assignment &&
                $assignment->status === 'completed' &&
                $assignment->technician,
                422,
                'A completed technician assignment is required to submit a rating.'
            );

            abort_if(
                $repairRequest->feedback()->exists(),
                409,
                'A rating has already been submitted for this service request.'
            );

            return $repairRequest->feedback()->create([
                'client_id' => $client->client_id,
                'technician_id' => $assignment->technician_id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]);
        });
        $repairRequest = RepairRequest::query()->findOrFail($requestId);
        $this->operationalNotifications->feedbackReceived(
            $repairRequest,
            (int) $feedback->rating,
            "feedback:{$feedback->feedback_id}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Your technician rating was submitted successfully.',
            'data' => $feedback,
        ], 201);
    }
}
