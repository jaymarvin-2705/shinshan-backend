<?php

namespace App\Repositories;

use App\Models\IssueCategory;
use App\Models\RepairRequest;
use App\Models\TechnicianProfile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportRepository
{
    public function getReport(array $filters): array
    {
        $query = $this->filteredRequests($filters);
        $summary = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN rr.status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN rr.status IN ('pending', 'assigned', 'on_the_way', 'in_progress') THEN 1 ELSE 0 END) as open")
            ->selectRaw("SUM(CASE WHEN rr.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->first();

        $total = (int) ($summary->total ?? 0);
        $completed = (int) ($summary->completed ?? 0);
        $averageCompletionMinutes = $this->getAverageCompletionMinutes(
            $query
        );

        return [
            'summary' => [
                'total' => $total,
                'completed' => $completed,
                'open' => (int) ($summary->open ?? 0),
                'cancelled' => (int) ($summary->cancelled ?? 0),
                'completion_rate' => $total > 0
                    ? round(($completed / $total) * 100, 1)
                    : 0,
                'average_completion_minutes' => $averageCompletionMinutes,
            ],
            'trend' => $this->getMonthlyTrend($query, $filters),
            'statuses' => $this->getStatusBreakdown($query),
            'categories' => $this->getCategoryBreakdown($query),
            'barangays' => $this->getBarangayBreakdown($query),
            'technician_performance' => $this->getTechnicianPerformance($filters),
            'categories_filter' => IssueCategory::query()
                ->orderBy('category_name')
                ->get(['category_id', 'category_name']),
            'technicians_filter' => TechnicianProfile::query()
                ->join('users', 'users.user_id', '=', 'technician_profiles.user_id')
                ->orderBy('users.fullname')
                ->get([
                    'technician_profiles.technician_id',
                    'users.fullname',
                ]),
            'barangays_filter' => RepairRequest::BARANGAYS,
            'requests' => RepairRequest::query()
                ->with([
                    'client.user',
                    'category',
                    'assignment.technician.user',
                ])
                ->whereIn('request_id', (clone $query)->select('rr.request_id'))
                ->latest('request_id')
                ->paginate(15),
        ];
    }

    public function getRequestsForExport(array $filters): LazyCollection
    {
        return RepairRequest::query()
            ->with([
                'client.user',
                'category',
                'assignment.technician.user',
            ])
            ->whereIn(
                'request_id',
                $this->filteredRequests($filters)->select('rr.request_id')
            )
            ->latest('request_id')
            ->lazy(500);
    }

    private function filteredRequests(array $filters): Builder
    {
        $query = DB::table('repair_requests as rr');

        if (!empty($filters['start_date'])) {
            $query->whereDate('rr.created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('rr.created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['status'])) {
            $query->where('rr.status', $filters['status']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('rr.category_id', $filters['category_id']);
        }

        if (!empty($filters['barangay'])) {
            $query->where('rr.barangay', $filters['barangay']);
        }

        if (!empty($filters['technician_id'])) {
            $query->whereExists(function (Builder $assignmentQuery) use ($filters) {
                $assignmentQuery
                    ->selectRaw('1')
                    ->from('job_assignments as ja')
                    ->whereColumn('ja.request_id', 'rr.request_id')
                    ->where('ja.technician_id', $filters['technician_id']);
            });
        }

        return $query;
    }

    private function getMonthlyTrend(Builder $query, array $filters): Collection
    {
        $periodExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', rr.created_at)",
            'pgsql' => "to_char(rr.created_at, 'YYYY-MM')",
            default => "DATE_FORMAT(rr.created_at, '%Y-%m')",
        };
        $completionPeriodExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', ru.created_at)",
            'pgsql' => "to_char(ru.created_at, 'YYYY-MM')",
            default => "DATE_FORMAT(ru.created_at, '%Y-%m')",
        };

        $requestCounts = (clone $query)
            ->selectRaw("$periodExpression as period")
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw($periodExpression)
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        $completionQuery = DB::table('repair_updates as ru')
            ->join(
                'job_assignments as ja',
                'ja.assignment_id',
                '=',
                'ru.assignment_id'
            )
            ->join('repair_requests as rr', 'rr.request_id', '=', 'ja.request_id')
            ->where('ru.status', 'completed')
            ->whereDate('ru.created_at', '>=', $filters['start_date'])
            ->whereDate('ru.created_at', '<=', $filters['end_date']);

        if (!empty($filters['status'])) {
            if ($filters['status'] !== 'completed') {
                $completionQuery->whereRaw('1 = 0');
            } else {
                $completionQuery->where('rr.status', 'completed');
            }
        }

        if (!empty($filters['category_id'])) {
            $completionQuery->where('rr.category_id', $filters['category_id']);
        }

        if (!empty($filters['barangay'])) {
            $completionQuery->where('rr.barangay', $filters['barangay']);
        }

        if (!empty($filters['technician_id'])) {
            $completionQuery->where('ja.technician_id', $filters['technician_id']);
        }

        $completionCounts = $completionQuery
            ->selectRaw("$completionPeriodExpression as period")
            ->selectRaw('COUNT(DISTINCT ja.request_id) as total')
            ->groupByRaw($completionPeriodExpression)
            ->get()
            ->keyBy('period');

        $period = CarbonImmutable::parse($filters['start_date'])->startOfMonth();
        $lastPeriod = CarbonImmutable::parse($filters['end_date'])->startOfMonth();
        $trend = collect();

        while ($period <= $lastPeriod) {
            $key = $period->format('Y-m');
            $requestCount = $requestCounts->get($key);
            $completionCount = $completionCounts->get($key);
            $trend->push([
                'period' => $key,
                'label' => $period->format('M Y'),
                'requests' => (int) ($requestCount->total ?? 0),
                'completed' => (int) ($completionCount->total ?? 0),
            ]);
            $period = $period->addMonth();
        }

        return $trend;
    }

    private function getStatusBreakdown(Builder $query): Collection
    {
        return (clone $query)
            ->select('rr.status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('rr.status')
            ->orderBy('rr.status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'total' => (int) $row->total,
            ]);
    }

    private function getCategoryBreakdown(Builder $query): Collection
    {
        return (clone $query)
            ->join('issue_categories as ic', 'ic.category_id', '=', 'rr.category_id')
            ->select('ic.category_id', 'ic.category_name')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('ic.category_id', 'ic.category_name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category_id' => $row->category_id,
                'category_name' => $row->category_name,
                'total' => (int) $row->total,
            ]);
    }

    private function getBarangayBreakdown(Builder $query): Collection
    {
        return (clone $query)
            ->whereNotNull('rr.barangay')
            ->where('rr.barangay', '!=', '')
            ->select('rr.barangay')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('rr.barangay')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'barangay' => $row->barangay,
                'total' => (int) $row->total,
            ]);
    }

    private function getTechnicianPerformance(array $filters): Collection
    {
        $query = $this->filteredRequests($filters);

        return $query
            ->join('job_assignments as ja', 'ja.request_id', '=', 'rr.request_id')
            ->join(
                'technician_profiles as tp',
                'tp.technician_id',
                '=',
                'ja.technician_id'
            )
            ->join('users as u', 'u.user_id', '=', 'tp.user_id')
            ->leftJoin('feedback as f', 'f.request_id', '=', 'rr.request_id')
            ->select('tp.technician_id', 'u.fullname')
            ->selectRaw('COUNT(ja.assignment_id) as assigned')
            ->selectRaw("SUM(CASE WHEN ja.status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw('AVG(f.rating) as average_rating')
            ->groupBy('tp.technician_id', 'u.fullname')
            ->orderByDesc('completed')
            ->orderBy('u.fullname')
            ->get()
            ->map(fn ($row) => [
                'technician_id' => $row->technician_id,
                'fullname' => $row->fullname,
                'assigned' => (int) $row->assigned,
                'completed' => (int) $row->completed,
                'average_rating' => $row->average_rating === null
                    ? null
                    : round((float) $row->average_rating, 2),
            ]);
    }

    private function getAverageCompletionMinutes(Builder $query): ?float
    {
        $completedUpdates = DB::table('repair_updates')
            ->select('assignment_id')
            ->selectRaw('MIN(created_at) as completed_at')
            ->where('status', 'completed')
            ->groupBy('assignment_id');

        $durationExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => 'AVG((julianday(completion_update.completed_at) - julianday(rr.created_at)) * 1440)',
            'pgsql' => 'AVG(EXTRACT(EPOCH FROM (completion_update.completed_at - rr.created_at)) / 60)',
            default => 'AVG(TIMESTAMPDIFF(MINUTE, rr.created_at, completion_update.completed_at))',
        };

        $result = (clone $query)
            ->join(
                'job_assignments as ja',
                'ja.request_id',
                '=',
                'rr.request_id'
            )
            ->joinSub(
                $completedUpdates,
                'completion_update',
                'completion_update.assignment_id',
                '=',
                'ja.assignment_id'
            )
            ->where('rr.status', 'completed')
            ->whereColumn(
                'completion_update.completed_at',
                '>=',
                'rr.created_at'
            )
            ->selectRaw("$durationExpression as average_minutes")
            ->first();

        return $result?->average_minutes === null
            ? null
            : round((float) $result->average_minutes, 1);
    }
}
