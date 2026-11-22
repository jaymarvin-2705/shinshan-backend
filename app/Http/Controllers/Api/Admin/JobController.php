<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\JobService;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function __construct(
        protected JobService $jobService
    ) {}

    public function index(Request $request)
    {
        $jobs = $this->jobService->getAllJobs(
            search: $request->query('search'),
            status: $request->query('status'),
            perPage: (int) $request->query('per_page', 10)
        );

        return response()->json([
            'success' => true,
            'message' => 'Jobs retrieved successfully.',
            'data' => $jobs,
        ]);
    }

    public function show(int $assignmentId)
    {
        $job = $this->jobService->getJobById(
            $assignmentId
        );

        return response()->json([
            'success' => true,
            'message' => 'Job retrieved successfully.',
            'data' => $job,
        ]);
    }
}