<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RepairRequest;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);

        return response()->json([
            'success' => true,
            'message' => 'Reports retrieved successfully.',
            'data' => $this->reportService->getReport($filters),
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $filename = sprintf(
            'service-reports-%s-to-%s.csv',
            $filters['start_date'],
            $filters['end_date']
        );

        return Response::streamDownload(function () use ($filters) {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'Request ID',
                'Created At',
                'Client',
                'Technician',
                'Title',
                'Category',
                'Status',
                'Priority',
                'Barangay',
            ]);

            foreach ($this->reportService->getRequestsForExport($filters) as $repairRequest) {
                fputcsv($output, [
                    $repairRequest->request_id,
                    $repairRequest->created_at,
                    $this->spreadsheetSafe($repairRequest->client?->user?->fullname ?? ''),
                    $this->spreadsheetSafe(
                        $repairRequest->assignment?->technician?->user?->fullname ?? ''
                    ),
                    $this->spreadsheetSafe($repairRequest->title),
                    $this->spreadsheetSafe(
                        $repairRequest->category?->category_name ?? ''
                    ),
                    $repairRequest->status,
                    $repairRequest->priority,
                    $this->spreadsheetSafe($repairRequest->barangay ?? ''),
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],
            'status' => [
                'nullable',
                Rule::in([
                    'pending',
                    'assigned',
                    'on_the_way',
                    'in_progress',
                    'completed',
                    'cancelled',
                ]),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('issue_categories', 'category_id'),
            ],
            'technician_id' => [
                'nullable',
                'integer',
                Rule::exists('technician_profiles', 'technician_id'),
            ],
            'barangay' => [
                'nullable',
                Rule::in(RepairRequest::BARANGAYS),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value)
            ? "'{$value}"
            : $value;
    }
}
