<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\FeedbackService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    public function __construct(
        protected FeedbackService $feedbackService
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'technician_id' => [
                'nullable',
                'integer',
                Rule::exists('technician_profiles', 'technician_id'),
            ],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:from_date',
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $results = $this->feedbackService->getAll(
            search: $validated['search'] ?? null,
            rating: isset($validated['rating']) ? (int) $validated['rating'] : null,
            technicianId: isset($validated['technician_id'])
                ? (int) $validated['technician_id']
                : null,
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
            perPage: (int) ($validated['per_page'] ?? 10)
        );

        return response()->json([
            'success' => true,
            'message' => 'Ratings and feedback retrieved successfully.',
            'data' => $results['feedback'],
            'summary' => $results['summary'],
            'technicians' => $results['technicians'],
        ]);
    }
}
