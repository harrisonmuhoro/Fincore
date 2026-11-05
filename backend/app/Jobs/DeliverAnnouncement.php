<?php

namespace App\Jobs;

use App\Domain\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class DeliverAnnouncement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $announcementId) {}

    public function handle(NotificationService $notifications): void
    {
        $announcement = DB::table('notification_announcements')->where('id', $this->announcementId)->where('status', 'published')->first();
        if (! $announcement) {
            return;
        }
        DB::table('users')->where('status', 'active')->orderBy('id')->select('id')->chunk(200, function ($users) use ($notifications, $announcement): void {
            foreach ($users as $user) {
                $notifications->deliver((int) $user->id, 'announcement:'.$announcement->id, 'announcement', $announcement->title, $announcement->body, ['announcement_id' => $announcement->id]);
            }
        });
    }
}
