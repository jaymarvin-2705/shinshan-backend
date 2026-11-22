<?php

namespace App\Services;

use App\Repositories\FeedbackRepository;

class FeedbackService
{
    public function __construct(
        protected FeedbackRepository $feedbackRepository
    ) {}

    public function getAll(
        ?string $search = null,
        ?int $rating = null,
        ?int $technicianId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        int $perPage = 10
    ): array {
        return [
            'feedback' => $this->feedbackRepository->getAll(
                search: $search,
                rating: $rating,
                technicianId: $technicianId,
                fromDate: $fromDate,
                toDate: $toDate,
                perPage: $perPage
            ),
            'summary' => $this->feedbackRepository->getSummary(),
            'technicians' => $this->feedbackRepository->getTechnicians(),
        ];
    }
}
