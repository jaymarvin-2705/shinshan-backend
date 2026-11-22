<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AnnouncementService
{
    public function listForAdmin(array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $query = Announcement::query()
            ->with('creator:user_id,fullname')
            ->withCount('notifications')
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->when($filters['audience'] ?? null, fn (Builder $query, string $audience) => $query->where('target_audience', $audience))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['published_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('publish_at', '>=', $date))
            ->when($filters['published_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('publish_at', '<=', $date))
            ->orderBy($sort, $direction);

        return $query->paginate((int) ($filters['per_page'] ?? 10));
    }

    public function summary(): array
    {
        $counts = Announcement::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (int) $counts->sum(),
            'published' => (int) ($counts['published'] ?? 0),
            'draft' => (int) ($counts['draft'] ?? 0),
            'scheduled' => (int) ($counts['scheduled'] ?? 0),
            'archived' => (int) ($counts['archived'] ?? 0),
        ];
    }

    public function create(User $admin, array $data, ?UploadedFile $attachment): Announcement
    {
        $storedPath = $this->storeAttachment($attachment);

        try {
            return DB::transaction(function () use ($admin, $data, $storedPath) {
                return Announcement::query()->create([
                    ...$data,
                    'admin_id' => $admin->user_id,
                    'status' => $data['status'] ?? 'draft',
                    'attachment_path' => $storedPath,
                ])->load('creator:user_id,fullname');
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFile($storedPath);
            throw $exception;
        }
    }

    public function update(
        Announcement $announcement,
        array $data,
        ?UploadedFile $attachment,
        bool $removeAttachment
    ): Announcement {
        if ($announcement->status === 'archived') {
            throw ValidationException::withMessages([
                'announcement' => ['Archived announcements cannot be edited.'],
            ]);
        }

        if ($announcement->status === 'published' && isset($data['status'])) {
            throw ValidationException::withMessages([
                'status' => ['A published announcement cannot be returned to draft or scheduled status.'],
            ]);
        }

        $storedPath = $this->storeAttachment($attachment);
        $oldPath = $announcement->attachment_path;

        try {
            $updatedAnnouncement = DB::transaction(function () use (
                $announcement,
                $data,
                $storedPath,
                $removeAttachment
            ) {
                $announcement = Announcement::query()
                    ->whereKey($announcement->announcement_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($announcement->status === 'archived') {
                    throw ValidationException::withMessages([
                        'announcement' => ['Archived announcements cannot be edited.'],
                    ]);
                }

                if ($announcement->status === 'published' && isset($data['status'])) {
                    throw ValidationException::withMessages([
                        'status' => ['A published announcement cannot be returned to draft or scheduled status.'],
                    ]);
                }

                $fields = $data;

                if ($storedPath !== null) {
                    $fields['attachment_path'] = $storedPath;
                } elseif ($removeAttachment) {
                    $fields['attachment_path'] = null;
                }

                $announcement->fill($fields);

                if ($announcement->status === 'scheduled' &&
                    (! $announcement->publish_at || $announcement->publish_at->isPast())) {
                    throw ValidationException::withMessages([
                        'publish_at' => ['Scheduled announcements must have a future publish date and time.'],
                    ]);
                }

                if ($announcement->expires_at &&
                    $announcement->publish_at &&
                    $announcement->expires_at->lessThanOrEqualTo($announcement->publish_at)) {
                    throw ValidationException::withMessages([
                        'expires_at' => ['The expiration date must be after the publish date.'],
                    ]);
                }

                $announcement->save();

                if ($announcement->status === 'published') {
                    $this->syncRecipients($announcement);
                }

                return $announcement->load('creator:user_id,fullname');
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFile($storedPath);
            throw $exception;
        }

        if ($storedPath !== null || $removeAttachment) {
            $this->deleteStoredFile($oldPath);
        }

        return $updatedAnnouncement;
    }

    public function publish(int $announcementId, ?Carbon $publishedAt = null): Announcement
    {
        return DB::transaction(function () use ($announcementId, $publishedAt) {
            $announcement = Announcement::query()
                ->whereKey($announcementId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($announcement->status === 'archived') {
                throw ValidationException::withMessages([
                    'announcement' => ['Archived announcements cannot be published.'],
                ]);
            }

            if ($announcement->expires_at &&
                $announcement->expires_at->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages([
                    'expires_at' => ['An expired announcement cannot be published.'],
                ]);
            }

            if ($announcement->status !== 'published') {
                $publishedAt ??= now();
                $announcement->update([
                    'status' => 'published',
                    'published_at' => $publishedAt,
                    'publish_at' => $publishedAt,
                ]);
            }

            $this->syncRecipients($announcement);

            return $announcement->load('creator:user_id,fullname');
        });
    }

    public function publishDue(): int
    {
        $dueAnnouncements = Announcement::query()
            ->where('status', 'scheduled')
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now())
            ->get(['announcement_id', 'publish_at']);

        $published = 0;

        foreach ($dueAnnouncements as $announcement) {
            $didPublish = DB::transaction(function () use ($announcement) {
                $locked = Announcement::query()
                    ->whereKey($announcement->announcement_id)
                    ->lockForUpdate()
                    ->first();

                if (! $locked ||
                    $locked->status !== 'scheduled' ||
                    ! $locked->publish_at ||
                    $locked->publish_at->isFuture()) {
                    return false;
                }

                if ($locked->expires_at &&
                    $locked->expires_at->lessThanOrEqualTo(now())) {
                    $locked->update(['status' => 'archived']);

                    return false;
                }

                $locked->update([
                    'status' => 'published',
                    'published_at' => $locked->publish_at,
                ]);
                $this->syncRecipients($locked);

                return true;
            });

            $published += (int) $didPublish;
        }

        return $published;
    }

    public function archive(Announcement $announcement): Announcement
    {
        $announcement->update(['status' => 'archived']);

        return $announcement->refresh()->load('creator:user_id,fullname');
    }

    public function delete(Announcement $announcement): void
    {
        $attachmentPath = $announcement->attachment_path;
        DB::transaction(fn () => $announcement->delete());
        $this->deleteStoredFile($attachmentPath);
    }

    public function feed(User $user, string $audience, int $perPage): LengthAwarePaginator
    {
        return $this->recipientQuery($user, $audience)
            ->with('announcement')
            ->latest('notification_id')
            ->paginate($perPage);
    }

    public function unreadCount(User $user, string $audience): int
    {
        return $this->recipientQuery($user, $audience)
            ->where('is_read', false)
            ->count();
    }

    public function markRead(User $user, string $audience, int $announcementId): Notification
    {
        $notification = $this->recipientQuery($user, $audience)
            ->where('announcement_id', $announcementId)
            ->firstOrFail();

        if (! $notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return $notification->load('announcement');
    }

    public function markAllRead(User $user, string $audience): int
    {
        return $this->recipientQuery($user, $audience)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function recipientAttachment(
        User $user,
        string $audience,
        int $announcementId
    ): string {
        $notification = $this->recipientQuery($user, $audience)
            ->where('announcement_id', $announcementId)
            ->with('announcement')
            ->firstOrFail();

        return $this->existingAttachmentPath($notification->announcement);
    }

    public function adminAttachmentPath(Announcement $announcement): string
    {
        return $this->existingAttachmentPath($announcement);
    }

    private function recipientQuery(User $user, string $audience): Builder
    {
        return Notification::query()
            ->where('user_id', $user->user_id)
            ->whereHas('announcement', fn (Builder $query) => $query->availableTo($audience));
    }

    private function syncRecipients(Announcement $announcement): void
    {
        $roles = match ($announcement->target_audience) {
            'clients' => ['Client'],
            'technicians' => ['Technician'],
            default => ['Client', 'Technician'],
        };

        $recipientQuery = User::query()
            ->where('status', 'active')
            ->whereHas('role', fn (Builder $query) => $query->whereIn('role_name', $roles));

        $now = now();
        $eligibleUserIds = (clone $recipientQuery)->select('user_id');

        $recipientQuery
            ->select('user_id')
            ->chunkById(500, function ($recipients) use ($announcement, $now) {
                $rows = $recipients
                    ->map(fn (User $user) => [
                        'announcement_id' => $announcement->announcement_id,
                        'user_id' => $user->user_id,
                        'title' => $announcement->title,
                        'message' => $announcement->content,
                        'is_read' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                Notification::query()->upsert(
                    $rows,
                    ['announcement_id', 'user_id'],
                    ['title', 'message', 'updated_at']
                );
            }, 'user_id', 'user_id');

        $announcement->notifications()
            ->whereNotIn('user_id', $eligibleUserIds)
            ->delete();
    }

    private function storeAttachment(?UploadedFile $attachment): ?string
    {
        if (! $attachment) {
            return null;
        }

        $path = $attachment->store('announcements', 'local');
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The announcement attachment could not be stored.');
        }

        return $path;
    }

    private function deleteStoredFile(?string $path): void
    {
        if ($path === null || ! Storage::disk('local')->exists($path)) {
            return;
        }

        if (! Storage::disk('local')->delete($path)) {
            Log::warning('Unable to remove an announcement attachment.', [
                'path' => $path,
            ]);
        }
    }

    private function existingAttachmentPath(Announcement $announcement): string
    {
        $path = $announcement->attachment_path;
        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        return $path;
    }
}
