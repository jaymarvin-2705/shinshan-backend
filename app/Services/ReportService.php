<?php

namespace App\Services;

use App\Repositories\ReportRepository;
use Illuminate\Support\LazyCollection;

class ReportService
{
    public function __construct(
        protected ReportRepository $reportRepository
    ) {}

    public function getReport(array $filters): array
    {
        return $this->reportRepository->getReport($filters);
    }

    public function getRequestsForExport(array $filters): LazyCollection
    {
        return $this->reportRepository->getRequestsForExport($filters);
    }
}
