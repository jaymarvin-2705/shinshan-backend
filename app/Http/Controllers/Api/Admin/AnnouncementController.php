<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function __construct(
        protected AnnouncementService $announcementService
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'audience' => ['nullable', Rule::in(Announcement::AUDIENCES)],
            'category' => ['nullable', Rule::in(Announcement::CATEGORIES)],
            'status' => ['nullable', Rule::in(Announcement::STATUSES)],
            'published_from' => ['nullable', 'date'],
            'published_to' => ['nullable', 'date', 'after_or_equal:published_from'],
            'sort' => ['nullable', Rule::in(['created_at', 'publish_at', 'published_at', 'title', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $announcements = $this->announcementService->listForAdmin($filters);
        $announcements->getCollection()->transform(
            fn (Announcement $announcement) => $this->present($announcement)
        );

        return response()->json([
            'success' => true,
            'message' => 'Announcements retrieved successfully.',
            'summary' => $this->announcementService->summary(),
            'data' => $announcements,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $this->validateSchedule($validated);

        $announcement = $this->announcementService->create(
            $request->user(),
            $validated,
            $request->file('attachment')
        );

        return response()->json([
            'success' => true,
            'message' => 'Announcement saved successfully.',
            'data' => $this->present($announcement),
        ], 201);
    }

    public function show(int $announcementId)
    {
        $announcement = Announcement::query()
            ->with('creator:user_id,fullname')
            ->withCount('notifications')
            ->findOrFail($announcementId);

        return response()->json([
            'success' => true,
            'message' => 'Announcement retrieved successfully.',
            'data' => $this->present($announcement),
        ]);
    }

    public function update(Request $request, int $announcementId)
    {
        $announcement = Announcement::query()->findOrFail($announcementId);
        $validated = $request->validate($this->rules(true));
        $this->validateSchedule($validated, $announcement);
        unset($validated['remove_attachment']);

        $announcement = $this->announcementService->update(
            $announcement,
            $validated,
            $request->file('attachment'),
            $request->boolean('remove_attachment')
        );

        return response()->json([
            'success' => true,
            'message' => 'Announcement updated successfully.',
            'data' => $this->present($announcement),
        ]);
    }

    public function publish(int $announcementId)
    {
        $announcement = $this->announcementService->publish($announcementId);

        return response()->json([
            'success' => true,
            'message' => 'Announcement published successfully.',
            'data' => $this->present($announcement),
        ]);
    }

    public function archive(int $announcementId)
    {
        $announcement = $this->announcementService->archive(
            Announcement::query()->findOrFail($announcementId)
        );

        return response()->json([
            'success' => true,
            'message' => 'Announcement archived successfully.',
            'data' => $this->present($announcement),
        ]);
    }

    public function destroy(int $announcementId)
    {
        $this->announcementService->delete(
            Announcement::query()->findOrFail($announcementId)
        );

        return response()->json([
            'success' => true,
            'message' => 'Announcement deleted successfully.',
        ]);
    }

    public function attachment(int $announcementId)
    {
        $announcement = Announcement::query()->findOrFail($announcementId);
        $path = $this->announcementService->adminAttachmentPath($announcement);

        return Storage::disk('local')->download($path);
    }

    private function rules(bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes' : 'required';

        $rules = [
            'title' => [$sometimes, 'string', 'max:150'],
            'content' => [$sometimes, 'string', 'max:10000'],
            'category' => [$sometimes, Rule::in(Announcement::CATEGORIES)],
            'target_audience' => [$sometimes, Rule::in(Announcement::AUDIENCES)],
            'status' => ['sometimes', Rule::in(['draft', 'scheduled'])],
            'publish_at' => [
                'nullable',
                'date',
                Rule::requiredIf(fn () => request('status') === 'scheduled'),
            ],
            'expires_at' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];

        if ($partial) {
            $rules['remove_attachment'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    private function validateSchedule(array $validated, ?Announcement $existing = null): void
    {
        $status = $validated['status'] ?? $existing?->status ?? 'draft';
        if ($status === 'scheduled') {
            $publishAt = array_key_exists('publish_at', $validated)
                ? $validated['publish_at']
                : $existing?->publish_at;
            abort_unless(
                $publishAt && Carbon::parse($publishAt)->isFuture(),
                422,
                'Scheduled announcements must have a future publish date and time.'
            );
        }

        $expiresAt = array_key_exists('expires_at', $validated)
            ? $validated['expires_at']
            : $existing?->expires_at;
        $publishAt = array_key_exists('publish_at', $validated)
            ? $validated['publish_at']
            : $existing?->publish_at;
        if ($expiresAt && $publishAt &&
            Carbon::parse($expiresAt)->lessThanOrEqualTo(Carbon::parse($publishAt))) {
            abort(422, 'The expiration date must be after the publish date.');
        }
    }

    private function present(Announcement $announcement): Announcement
    {
        $announcement->setAttribute(
            'attachment_url',
            $announcement->attachment_path
                ? route('admin.announcements.attachment', [
                    'announcementId' => $announcement->announcement_id,
                ])
                : null
        );

        return $announcement;
    }
}
