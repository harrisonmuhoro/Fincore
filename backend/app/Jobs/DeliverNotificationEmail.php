<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DeliverNotificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $userId, public readonly string $eventId, public readonly string $title, public readonly string $body) {}

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(): void
    {
        $delivery = DB::table('notification_deliveries')->where('user_id', $this->userId)->where('event_id', $this->eventId)->where('channel', 'email')->first();
        if (! $delivery || $delivery->status === 'sent') {
            return;
        }
        try {
            $user = User::query()->findOrFail($this->userId);
            Mail::raw($this->body, fn ($message) => $message->to($user->email)->subject($this->title));
            DB::table('notification_deliveries')->where('id', $delivery->id)->update(['status' => 'sent', 'attempts' => $delivery->attempts + 1, 'last_error' => null, 'updated_at' => now()]);
        } catch (\Throwable $exception) {
            DB::table('notification_deliveries')->where('id', $delivery->id)->update(['status' => 'retrying', 'attempts' => $delivery->attempts + 1, 'last_error' => $exception::class, 'updated_at' => now()]);
            throw $exception;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        DB::table('notification_deliveries')->where('user_id', $this->userId)->where('event_id', $this->eventId)->where('channel', 'email')->update(['status' => 'failed', 'last_error' => $exception ? $exception::class : 'Queue retry limit reached.', 'updated_at' => now()]);
    }
}
