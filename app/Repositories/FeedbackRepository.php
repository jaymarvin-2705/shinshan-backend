<?php

namespace App\Repositories;

use App\Models\Feedback;
use App\Models\TechnicianProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FeedbackRepository
{
    public function getAll(
        ?string $search = null,
        ?int $rating = null,
        ?int $technicianId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        return $this->filteredQuery(
            search: $search,
            rating: $rating,
            technicianId: $technicianId,
            fromDate: $fromDate,
            toDate: $toDate
        )
            ->with([
                'repairRequest.category',
                'client.user',
                'technician.user',
            ])
            ->latest('feedback_id')
            ->paginate($perPage);
    }

    public function getSummary(): array
    {
        $ratingCounts = Feedback::query()
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        return [
            'total' => (int) Feedback::count(),
            'average_rating' => round((float) (Feedback::avg('rating') ?? 0), 2),
            'rating_counts' => collect(range(1, 5))
                ->mapWithKeys(fn (int $rating) => [
                    $rating => (int) ($ratingCounts[$rating] ?? 0),
                ]),
        ];
    }

    public function getTechnicians(): Collection
    {
        $technicianIds = Feedback::query()
            ->distinct()
            ->pluck('technician_id');

        return TechnicianProfile::query()
            ->with('user')
            ->whereIn('technician_id', $technicianIds)
            ->get()
            ->sortBy(fn (TechnicianProfile $technician) => $technician->user?->fullname)
            ->values()
            ->map(fn (TechnicianProfile $technician) => [
                'technician_id' => $technician->technician_id,
                'fullname' => $technician->user?->fullname ?? 'Unknown technician',
            ]);
    }

    private function filteredQuery(
        ?string $search,
        ?int $rating,
        ?int $technicianId,
        ?string $fromDate,
        ?string $toDate
    ): Builder {
        return Feedback::query()
            ->when($rating, fn (Builder $query) => $query->where('rating', $rating))
            ->when(
                $technicianId,
                fn (Builder $query) => $query->where('technician_id', $technicianId)
            )
            ->when(
                $fromDate,
                fn (Builder $query) => $query->whereDate('created_at', '>=', $fromDate)
            )
            ->when(
                $toDate,
                fn (Builder $query) => $query->whereDate('created_at', '<=', $toDate)
            )
            ->when($search, function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('comment', 'like', "%{$search}%")
                        ->orWhereHas('repairRequest', function (Builder $query) use ($search) {
                            $query->where('title', 'like', "%{$search}%");
                        })
                        ->orWhereHas('client.user', function (Builder $query) use ($search) {
                            $query->where('fullname', 'like', "%{$search}%");
                        })
                        ->orWhereHas('technician.user', function (Builder $query) use ($search) {
                            $query->where('fullname', 'like', "%{$search}%");
                        });
                });
            });
    }
}
