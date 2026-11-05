<?php

namespace App\Domain\Notifications;

use App\Jobs\DeliverNotificationEmail;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function deliver(int $userId, string $eventId, string $category, string $title, string $body, array $data = []): bool
    {
        $user = User::query()->findOrFail($userId);
        $preference = DB::table('notification_preferences')->where('user_id', $userId)->where('category', $category)->first();
        $inApp = $preference?->in_app_enabled ?? true;
        $email = $preference?->email_enabled ?? false;
        $created = false;
        if ($inApp) {
            $created = DB::table('user_notifications')->insertOrIgnore([
                'user_id' => $userId,
                'event_id' => $eventId,
                'category' => $category,
                'title' => $title,
                'body' => $body,
                'data' => $data ? json_encode($data, JSON_THROW_ON_ERROR) : null,
                'created_at' => now(),
            ]) === 1;
        }
        if ($email) {
            $mailDelivery = DB::table('notification_deliveries')->insertOrIgnore([
                'user_id' => $userId, 'event_id' => $eventId, 'channel' => 'email', 'status' => 'queued',
                'attempts' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]) === 1;
            if ($mailDelivery) {
                DeliverNotificationEmail::dispatch((int) $user->id, $eventId, $title, $body)->afterCommit();
            }
        }

        return $created || ($email && isset($mailDelivery) && $mailDelivery);
    }
}
