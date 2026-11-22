<?php

namespace App\Console\Commands;

use App\Services\AnnouncementService;
use Illuminate\Console\Command;

class PublishScheduledAnnouncements extends Command
{
    protected $signature = 'announcements:publish-scheduled';

    protected $description = 'Publish announcements whose scheduled time has arrived.';

    public function handle(AnnouncementService $announcementService): int
    {
        $published = $announcementService->publishDue();
        $this->info("Published {$published} scheduled announcement(s).");

        return self::SUCCESS;
    }
}
